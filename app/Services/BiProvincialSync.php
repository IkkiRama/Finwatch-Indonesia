<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\DataSource;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BiProvincialSync
{
    public function __construct(private BiSekiProvincialAdapter $parser) {}

    public function synchronize(SyncRun $run, DataSource $source, OfficialDownload $download, DatasetImporter $importer, callable $mark): void
    {
        if (! $source->enabled) {
            $source->update(['last_checked_at' => now()]);
            $run->update(['finished_at' => now()]);
            $mark('manual_required', 100, 'Sumber BI dinonaktifkan pengelola. Tidak ada unduhan atau perubahan data publik.');

            return;
        }
        if ($source->code !== 'BI' || $source->adapter !== BiSekiProvincialAdapter::PROFILE || $source->download_url !== BiSekiProvincialAdapter::SOURCES['credit']['url']) {
            throw new RuntimeException('Konfigurasi sumber BI tidak sesuai profil dua tabel provinsi yang diverifikasi.');
        }
        $downloads = [];
        foreach (BiSekiProvincialAdapter::SOURCES as $kind => $definition) {
            $mark('downloading', $kind === 'credit' ? 25 : 40, 'Mengambil tabel BI '.$definition['table'].' dari situs resmi.');
            $response = $download->fetchWithMetadata($definition['url'], ['bi.go.id', 'www.bi.go.id']);
            $downloads[$kind] = array_replace($response, ['sha256' => hash('sha256', $response['content'])]);
        }
        $checksum = hash('sha256', $downloads['credit']['sha256'].':'.$downloads['deposits']['sha256']);
        $datasetVersion = BiSekiProvincialAdapter::EDITION_ID.'-'.substr($checksum, 0, 12);
        $known = true;
        foreach (BiSekiProvincialAdapter::SOURCES as $kind => $definition) {
            $known = $known && hash_equals($definition['sha256'], $downloads[$kind]['sha256']);
        }
        $dataset = Dataset::where('code', BiSekiProvincialAdapter::DATASET_CODE)->first();
        if ($dataset && $dataset->data_source_id !== $source->id) {
            throw new RuntimeException('Dataset provinsi BI telah digunakan oleh sumber lain.');
        }
        $existing = $dataset?->versions()->whereIn('status', ['needs_review', 'published'])->latest('number')->get()->first(fn (DatasetVersion $version): bool => ($version->rows[0]['dataset_version'] ?? null) === $datasetVersion);
        if ($known && $existing && Storage::disk('local')->exists($existing->raw_path)) {
            $run->update(['version_id' => $existing->id, 'finished_at' => now()]);
            $source->update(['last_checksum' => $checksum, 'last_checked_at' => now()]);
            $mark('no_new_data', 100, 'Kedua tabel BI sama dengan edisi yang telah diverifikasi. Tidak ada versi baru.');

            return;
        }
        $directory = 'snapshots/bi-seki/'.$checksum;
        $files = [];
        foreach (BiSekiProvincialAdapter::SOURCES as $kind => $definition) {
            $path = $directory.'/'.basename($definition['url']);
            if (! Storage::disk('local')->put($path, $downloads[$kind]['content'])) {
                throw new RuntimeException('Snapshot privat BI belum dapat disimpan.');
            }
            $files[] = [
                'table' => $definition['table'], 'url' => $definition['url'], 'path' => $path,
                'resolved_url' => $downloads[$kind]['url'], 'sha256' => $downloads[$kind]['sha256'],
                'last_modified' => $downloads[$kind]['last_modified'],
            ];
        }
        $manifest = [
            'profile' => BiSekiProvincialAdapter::PROFILE, 'geography_id' => BiProvinceRegistry::GEOGRAPHY_ID,
            'retrieved_at' => $downloads['deposits']['retrieved_at'], 'checksum' => $checksum,
            'dataset_version' => $known ? $datasetVersion : null,
            'release_date' => $known ? BiSekiProvincialAdapter::RELEASE_DATE : null,
            'publication_url' => $known ? BiSekiProvincialAdapter::PUBLICATION_URL : null,
            'files' => $files,
        ];
        $manifestPath = $directory.'/manifest.json';
        if (! Storage::disk('local')->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))) {
            throw new RuntimeException('Manifest sumber BI belum dapat disimpan.');
        }
        if (! $known) {
            $source->update(['last_checked_at' => now()]);
            $run->update(['version_id' => null, 'finished_at' => now()]);
            $mark('manual_required', 100, 'Berkas BI berubah dari edisi September 2026. Snapshot disimpan privat untuk pemeriksaan edisi, tanggal rilis, struktur tabel dan kode wilayah. Data publik sebelumnya tetap digunakan.');
            AuditService::record('sync.profile_review_required', User::find($run->requested_by), ['source_id' => $source->id, 'sync_run_id' => $run->id]);

            return;
        }
        $mark('parsing', 60, 'Membaca dua tabel BI dan mencocokkan 34 baris provinsi, periode bulanan, serta satuan.');
        $rows = [];
        $periodSets = [];
        foreach (BiSekiProvincialAdapter::SOURCES as $kind => $definition) {
            $path = $directory.'/'.basename($definition['url']);
            $parsed = $this->parser->parse(Storage::disk('local')->path($path), $kind, $downloads[$kind]['retrieved_at'], $datasetVersion);
            $periodSets[$kind] = array_values(array_unique(array_column($parsed, 'period_date')));
            $rows = array_merge($rows, $parsed);
        }
        if ($periodSets['credit'] !== $periodSets['deposits']) {
            throw new RuntimeException('Periode dua tabel BI tidak cocok; tinjau kedua edisi sebelum menerbitkan.');
        }
        $mark('validating', 80, 'Memvalidasi angka dan provenance; tanggal rilis mengikuti publikasi resmi 15 Oktober 2026, bukan tanggal unduhan.');
        $dataset = Dataset::firstOrCreate(['code' => BiSekiProvincialAdapter::DATASET_CODE], ['name' => 'BI | Kredit dan simpanan provinsi', 'data_source_id' => $source->id, 'status' => 'draft']);
        if ($dataset->data_source_id !== $source->id) {
            throw new RuntimeException('Dataset provinsi BI telah digunakan oleh sumber lain.');
        }
        $version = $importer->stage($dataset, $rows, $manifestPath, User::find($run->requested_by));
        $run->update(['version_id' => $version->id, 'finished_at' => now()]);
        if ($version->issues) {
            $mark('failed', 100, 'Validasi tabel BI gagal. Periksa masalah pada versi yang disiapkan; data publik tidak diubah.');

            return;
        }
        $source->update(['last_checksum' => $checksum, 'last_checked_at' => now()]);
        $mark('needs_review', 100, 'Kedua tabel BI selesai divalidasi. Menunggu persetujuan pengelola; publikasi lama tetap digunakan sampai versi ini disetujui.');
    }
}
