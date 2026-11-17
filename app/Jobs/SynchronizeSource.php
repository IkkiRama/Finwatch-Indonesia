<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Models\DataSource;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\BiProvincialSync;
use App\Services\BiSekiProvincialAdapter;
use App\Services\DatasetImporter;
use App\Services\OfficialDownload;
use App\Services\SourceAdapters\Registry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SynchronizeSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public int $runId) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('finwatch-source-'.$this->sourceId()))->shared()->releaseAfter(15)->expireAfter(120)];
    }

    private function sourceId(): int
    {
        return (int) SyncRun::whereKey($this->runId)->value('data_source_id');
    }

    public function failed(?Throwable $exception): void
    {
        $run = SyncRun::find($this->runId);
        if ($run && ! in_array($run->status, ['success', 'no_new_data', 'needs_review', 'manual_required'], true)) {
            $this->mark($run, 'failed', 100, 'Proses berhenti setelah batas waktu/percobaan. Periksa worker dan ulangi setelah penyebab diperbaiki.');
            $run->update(['finished_at' => now()]);
        }
    }

    private function mark(SyncRun $run, string $status, int $progress, string $message): void
    {
        $logs = $run->fresh()->logs ?? [];
        $logs[] = ['at' => now()->toIso8601String(), 'status' => $status, 'message' => $message];
        $run->update(['status' => $status, 'progress' => $progress, 'logs' => array_slice($logs, -50)]);
    }

    public function handle(OfficialDownload $download, DatasetImporter $importer): void
    {
        $run = SyncRun::findOrFail($this->runId);
        if (in_array($run->status, ['no_new_data', 'needs_review', 'manual_required', 'success'], true)) {
            return;
        }
        $source = DataSource::findOrFail($run->data_source_id);
        $adapter = app(Registry::class)->for($source);
        $run->update(['started_at' => now(), 'finished_at' => null]);
        $this->mark($run, 'checking', 10, 'Memeriksa konfigurasi adapter '.$source->code.'.');
        try {
            if ($source->code === 'BI' && $source->adapter === BiSekiProvincialAdapter::PROFILE) {
                app(BiProvincialSync::class)->synchronize($run, $source, $download, $importer, fn (string $status, int $progress, string $message) => $this->mark($run, $status, $progress, $message));

                return;
            }
            $discovered = $adapter->discover($source);
            if (! $source->enabled || ! $discovered) {
                $this->mark($run, 'manual_required', 100, 'Pengambil data otomatis untuk publikasi ini belum dikonfigurasi. Unduh tabel asli dari situs resmi, sesuaikan kolom ke template, lalu unggah CSV/XLSX melalui Dataset & validasi.');
                $source->update(['last_checked_at' => now()]);
                $run->update(['finished_at' => now()]);

                return;
            }
            $extension = strtolower(pathinfo(parse_url($source->download_url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (! in_array($extension, ['csv', 'xlsx'], true)) {
                throw new \RuntimeException('Format unduhan belum didukung.');
            }
            $this->mark($run, 'downloading', 25, 'Mengambil berkas dari domain resmi yang diizinkan.');
            $content = $download->fetch($discovered, array_values(array_intersect($adapter->hosts(), $source->allowed_hosts ?? $adapter->hosts())));
            $checksum = hash('sha256', $content);
            if ($source->last_checksum === $checksum) {
                $this->mark($run, 'no_new_data', 100, 'Berkas resmi belum berubah sejak pemeriksaan sebelumnya.');
                $source->update(['last_checked_at' => now()]);
                $run->update(['finished_at' => now()]);

                return;
            }
            $path = 'snapshots/'.Str::uuid().'.'.$extension;
            Storage::disk('local')->put($path, $content);
            $this->mark($run, 'parsing', 45, 'Membaca tabel normalisasi dan memeriksa batas berkas.');
            $rows = $adapter->parse(Storage::disk('local')->path($path), $extension, $source);
            $this->mark($run, 'validating', 65, 'Memvalidasi satuan, periode, provenance, dan duplikasi.');
            $dataset = Dataset::firstOrCreate(['code' => strtolower($source->code).'-synchronized'], ['name' => $source->name.' | sinkronisasi', 'data_source_id' => $source->id, 'status' => 'draft']);
            $version = $importer->stage($dataset, $rows, $path, User::find($run->requested_by));
            $run->update(['version_id' => $version->id]);
            if ($version->issues) {
                $this->mark($run, 'failed', 100, 'Validasi gagal; publikasi sebelumnya tetap digunakan. Periksa daftar masalah pada versi staging.');
            } else {
                $this->mark($run, 'needs_review', 100, 'Validasi selesai. Menunggu persetujuan pengelola sebelum publikasi dan perhitungan ulang.');
                $source->update(['last_checksum' => $checksum, 'last_checked_at' => now()]);
            }
            $run->update(['finished_at' => now()]);
        } catch (Throwable $exception) {
            $retrying = $this->job && $this->attempts() < $this->tries;
            $this->mark($run, $retrying ? 'retrying' : 'failed', $retrying ? 0 : 100, $retrying ? 'Percobaan gagal; antrean akan mencoba kembali sesuai backoff. Data publik tidak berubah.' : 'Pengambilan/pemrosesan gagal. Periksa URL, format, koneksi, dan log server; data publik tidak diubah.');
            $run->update(['finished_at' => now()]);
            throw $exception;
        }
    }
}
