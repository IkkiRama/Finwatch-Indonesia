<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\DataSource;
use App\Models\RiskRule;
use App\Services\DatasetImporter;
use App\Services\OjkSpiAdapter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class OfficialDataSeeder extends Seeder
{
    public const SPI_URL = 'https://ojk.go.id/id/kanal/perbankan/data-dan-statistik/statistik-perbankan-indonesia/Documents/Pages/Statistik-Perbankan-Indonesia---Juni-2025/STATISTIK%20PERBANKAN%20INDONESIA%20%20-Juni%202025.xlsx';

    public const SNLIK_URL = 'https://ojk.go.id/id/berita-dan-kegiatan/siaran-pers/Pages/OJK-dan-BPS-Umumkan-Hasil-Survei-Nasional-Literasi-dan-Inklusi-Keuangan-SNLIK-Tahun-2025.aspx';

    public const RDK_URL = 'https://ojk.go.id/id/berita-dan-kegiatan/siaran-pers/Pages/RDKB-Agustus-2025.aspx';

    public function run(): void
    {
        $source = DataSource::where('code', 'OJK')->firstOrFail();
        $file = database_path('sources/ojk-spi-june-2025.xlsx');
        if (! is_file($file)) {
            return;
        }
        $rows = app(OjkSpiAdapter::class)->parse($file, self::SPI_URL, $this->retrieved(self::SPI_URL));
        $this->publish('ojk-spi-june-2025', 'SPI Bank Umum · vintage Juni 2025', $source, $rows, $file);
        if (! $source->download_url) {
            $source->update(['download_url' => self::SPI_URL, 'adapter' => 'ojk_spi_2025_06', 'method' => 'official_file', 'status' => 'configured', 'allowed_hosts' => ['ojk.go.id', 'www.ojk.go.id', 'data.ojk.go.id'], 'last_checksum' => hash_file('sha256', $file), 'last_checked_at' => $this->retrieved(self::SPI_URL), 'description' => 'Snapshot historis SPI Juni 2025; profil parser khusus arsip ini. Sejak periode Juli 2025, SPI dialihkan ke Portal Data OJK; tidak mengklaim arsip ini sebagai rilis terbaru.']);
        }
        $rows = [];
        $method = 'SNLIK, Metode Keberlanjutan. Tahun ditandai tanggal 01-01 sebagai konvensi periode tahunan, bukan tanggal wawancara. Survei 2025: 22 Januari–11 Februari, usia 15–79, 10.800 responden; cakupan DNKI berbeda dan tidak dicampur. Perbandingan nasional 2024/2025 mengikuti pernyataan komparabilitas pada rilis OJK/BPS.';
        foreach ([[2024, 'Indonesia', 65.43, 75.02], [2025, 'Indonesia', 66.46, 80.51], [2025, 'Perkotaan', 70.89, 83.61], [2025, 'Perdesaan', 59.60, 75.70], [2025, 'Laki-laki', 67.32, 80.73], [2025, 'Perempuan', 65.58, 80.28]] as [$year,$scope,$literacy,$inclusion]) {
            foreach ([['literacy', 'Indeks literasi keuangan', $literacy], ['inclusion', 'Indeks inklusi keuangan', $inclusion]] as [$code,$label,$value]) {
                $rows[] = $this->row($code, $label, $year.'-01-01', $value, '%', 'annual', $scope, 'inclusion', self::SNLIK_URL, '2025-05-02', 'SNLIK-2025-Keberlanjutan', $method);
            }
        }
        $this->publish('snlik-2025-keberlanjutan', 'SNLIK · Metode Keberlanjutan 2024–2025', DataSource::where('code', 'SNLIK')->firstOrFail(), $rows, database_path('sources/ojk-snlik-2025.html'));
        $npl = $this->row('npl_gross', 'NPL Gross', '2025-06-30', 2.22, '%', 'monthly', 'Indonesia', 'banking', self::RDK_URL, '2025-09-04', 'RDKB-Agustus-2025', 'Agregat perbankan, observasi Juni 2025 yang disebut sebagai pembanding dalam rilis RDKB Agustus 2025. Hanya satu periode dikurasi; tidak menyimpulkan tren dari satu titik.');
        $this->publish('ojk-npl-june-2025', 'NPL Gross · observasi Juni 2025', $source, [$npl], database_path('sources/ojk-rdk-august-2025.html'));
        $rows = [];
        foreach ([['appk_services', 'Permintaan layanan APPK', 318908, 'layanan', '2025-08-15', 'Jumlah kumulatif 1 Januari–15 Agustus 2025; termasuk pengaduan, jangan dijumlahkan kembali dengan jumlah pengaduan.'], ['appk_complaints', 'Pengaduan APPK', 31456, 'pengaduan', '2025-08-15', 'Jumlah kumulatif 1 Januari–15 Agustus 2025; bukan jumlah bulanan Agustus.'], ['illegal_complaints', 'Pengaduan entitas ilegal', 14634, 'pengaduan', '2025-08-29', 'Jumlah kumulatif 1 Januari–29 Agustus 2025; ruang lingkup berbeda dari APPK.'], ['illegal_entities_stopped', 'Entitas/penawaran ilegal dihentikan', 1840, 'entitas/penawaran', '2025-08-29', 'Jumlah kumulatif 1 Januari–29 Agustus 2025: 1.556 pinjol ilegal dan 284 penawaran investasi ilegal; bukan jumlah situs atau kerugian.']] as [$code,$label,$value,$unit,$date,$note]) {
            $rows[] = $this->row($code, $label, $date, $value, $unit, 'snapshot', 'Indonesia', 'consumer', self::RDK_URL, '2025-09-04', 'RDKB-Agustus-2025', $note.' Vintage historis, jangan dibandingkan langsung dengan periode kumulatif berbeda.');
        }
        $this->publish('ojk-consumer-august-2025', 'Pelindungan konsumen · periode kumulatif 2025', DataSource::where('code', 'PEPK')->firstOrFail(), $rows, database_path('sources/ojk-rdk-august-2025.html'));
        RiskRule::firstOrCreate(['name' => 'Perubahan LDR antarobservasi'], ['indicator_code' => 'ldr', 'operator' => 'change_gt', 'threshold' => 3, 'minimum_samples' => 6, 'severity' => 'watch', 'enabled' => true, 'methodology' => 'Aturan eksplorasi: perubahan LDR di atas 3 pp pada observasi berurutan dengan definisi sama dan minimal enam observasi. Parameter penelitian, bukan threshold kesehatan regulator; periksa jarak tanggal dan kondisi data.']);
    }

    /** Original capture times stay fixed because checkout/deployment changes file modification times. */
    private function retrieved(string $url): string
    {
        return match ($url) {
            self::SPI_URL => '2026-10-25T21:44:55Z',
            self::SNLIK_URL => '2026-10-25T21:44:56Z',
            self::RDK_URL => '2026-10-25T22:07:07Z',
            default => throw new \InvalidArgumentException('Sumber snapshot tidak dikenal.'),
        };
    }

    private function row(string $code, string $label, string $date, float $value, string $unit, string $frequency, string $scope, string $sector, string $url, string $release, string $vintage, string $note): array
    {
        return ['indicator_code' => $code, 'label' => $label, 'period_date' => $date, 'value' => number_format($value, 8, '.', ''), 'unit' => $unit, 'frequency' => $frequency, 'scope' => $scope, 'source_url' => $url, 'release_date' => $release, 'retrieved_at' => $this->retrieved($url), 'dataset_version' => $vintage, 'methodology_note' => $note, 'sector' => $sector, 'segment' => 'aggregate'];
    }

    private function publish(string $code, string $name, DataSource $source, array $rows, string $file): void
    {
        $dataset = Dataset::firstOrCreate(['code' => $code], ['name' => $name, 'data_source_id' => $source->id, 'status' => 'draft', 'description' => 'Snapshot resmi terkurasi; bukan data waktu nyata.']);
        if ($dataset->current_version_id || ! is_file($file)) {
            return;
        }
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $extension = in_array($extension, ['xlsx', 'csv'], true) ? $extension : 'html';
        $path = 'snapshots/official-'.str_replace('-', '_', $code).'.'.$extension;
        Storage::disk('local')->put($path, file_get_contents($file));
        $version = app(DatasetImporter::class)->stage($dataset, $rows, $path, null);
        if ($version->issues) {
            throw new \RuntimeException('Snapshot resmi gagal validasi: '.json_encode($version->issues));
        }
        app(DatasetImporter::class)->publish($version, null);
    }
}
