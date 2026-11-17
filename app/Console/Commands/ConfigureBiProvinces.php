<?php

namespace App\Console\Commands;

use App\Models\DatasetVersion;
use App\Models\DataSource;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Analytics;
use App\Services\AuditService;
use App\Services\BiProvincialSync;
use App\Services\BiSekiProvincialAdapter;
use App\Services\DatasetImporter;
use App\Services\OfficialDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ConfigureBiProvinces extends Command
{
    protected $signature = 'finwatch:configure-bi-provinces {email : Email akun Google pengelola terverifikasi} {--publish : Setujui dan terbitkan versi dua tabel yang sudah diverifikasi}';

    protected $description = 'Konfigurasikan dan periksa dua tabel provinsi BI dengan snapshot, validasi, dan tinjauan edisi.';

    public function handle(BiProvincialSync $sync, OfficialDownload $download, DatasetImporter $importer): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();
        if (! $user || ! $user->hasVerifiedEmail() || ! $user->hasPermission('data.manage') || ! $user->hasPermission('sources.manage') || ! $user->socialIdentities()->where('provider', 'google')->whereNotNull('verified_email_at')->exists()) {
            $this->error('Gunakan akun Google aktif terverifikasi yang memiliki izin data.manage dan sources.manage. Command tidak membuat akun atau melewati verifikasi akun.');

            return self::FAILURE;
        }
        if (json_decode(DB::table('system_settings')->where('key', 'sync_enabled')->value('value') ?? 'true', true) === false) {
            $this->error('Sinkronisasi dinonaktifkan pengelola. Pengaturan tidak diubah oleh command ini.');

            return self::FAILURE;
        }
        $run = null;
        try {
            [$source, $run] = DB::transaction(function () use ($user): array {
                DataSource::firstOrCreate(['code' => 'BI'], ['name' => 'Bank Indonesia', 'url' => 'https://www.bi.go.id/id/statistik/ekonomi-keuangan/seki/default.aspx', 'description' => 'Publikasi Statistik Ekonomi dan Keuangan Indonesia.']);
                $source = DataSource::where('code', 'BI')->lockForUpdate()->firstOrFail();
                if (SyncRun::where('data_source_id', $source->id)->whereIn('status', ['queued', 'checking', 'downloading', 'parsing', 'validating', 'retrying'])->exists()) {
                    throw new RuntimeException('Pemeriksaan BI masih berjalan. Tunggu proses tersebut selesai sebelum menjalankan ulang.');
                }
                $source->update([
                    'name' => 'Bank Indonesia', 'url' => 'https://www.bi.go.id/id/statistik/ekonomi-keuangan/seki/default.aspx',
                    'description' => 'Dua tabel SEKI BI: I.12 kredit rupiah dan valas menurut lokasi proyek; I.19 simpanan masyarakat rupiah dan valas menurut provinsi. Edisi September 2026 dirilis 15 Oktober 2026, observasi terbaru Agustus 2026. Berkas edisi baru memerlukan tinjauan profil sebelum diterbitkan.',
                    'method' => 'official_file', 'status' => 'configured', 'adapter' => BiSekiProvincialAdapter::PROFILE,
                    'download_url' => BiSekiProvincialAdapter::SOURCES['credit']['url'], 'frequency' => 'monthly',
                    'allowed_hosts' => ['bi.go.id', 'www.bi.go.id'], 'enabled' => true,
                ]);
                AuditService::record('source.configured', $user, ['source_id' => $source->id, 'channel' => 'cli']);
                $run = SyncRun::create(['data_source_id' => $source->id, 'requested_by' => $user->id, 'status' => 'checking', 'progress' => 10, 'started_at' => now(), 'logs' => []]);
                AuditService::record('sync.started', $user, ['source_id' => $source->id, 'sync_run_id' => $run->id, 'channel' => 'cli']);

                return [$source, $run];
            });
            $mark = function (string $status, int $progress, string $message) use ($run): void {
                $logs = $run->fresh()->logs ?? [];
                $logs[] = ['at' => now()->toIso8601String(), 'status' => $status, 'message' => $message];
                $run->update(['status' => $status, 'progress' => $progress, 'logs' => array_slice($logs, -50)]);
                $this->line($progress.'% | '.$message);
            };
            $sync->synchronize($run, $source, $download, $importer, $mark);
            $run->refresh();
            if (! $run->version_id || ! in_array($run->status, ['needs_review', 'no_new_data'], true)) {
                $this->error('Pemeriksaan selesai tanpa versi baru yang dapat diterbitkan. Buka log pemeriksaan BI.');

                return self::FAILURE;
            }
            $version = DatasetVersion::findOrFail($run->version_id);
            if ($this->option('publish')) {
                $version = $importer->publish($version, $user);
                $source->update(['status' => 'active']);
                SyncRun::where('version_id', $version->id)->whereIn('status', ['needs_review', 'no_new_data'])->update(['status' => 'success', 'progress' => 100, 'finished_at' => now()]);
                app(Analytics::class)->signals();
                $this->info('Versi BI #'.$version->number.' diterbitkan dengan '.count($version->rows).' observasi. Dua indikator siap dibandingkan.');
            } else {
                $this->info('Versi BI #'.$version->number.' tersedia untuk tinjauan. Tidak ada publikasi otomatis.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($run) {
                $logs = $run->fresh()->logs ?? [];
                $logs[] = ['at' => now()->toIso8601String(), 'status' => 'failed', 'message' => 'Pemeriksaan BI gagal. Data publik sebelumnya tetap dipertahankan.'];
                $run->update(['status' => 'failed', 'progress' => 100, 'finished_at' => now(), 'logs' => array_slice($logs, -50)]);
            }
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
