<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class ProvinceComparison
{
    public function __construct(private RegionRegistry $regions, private ObservationComparability $comparability, private BiProvinceRegistry $biRegions, private BiProvincialData $bi) {}

    public function indicators(): array
    {
        $survey = [
            ['code' => 'literacy', 'label' => 'Literasi keuangan', 'unit' => '%', 'available' => true, 'reason' => 'Arsip SNLIK OJK 2022 tersedia untuk 34 wilayah.', 'definition' => 'Indeks literasi berdasarkan pengetahuan, keterampilan, keyakinan, sikap dan perilaku menurut SNLIK 2022.', 'location_basis' => 'Provinsi tempat tinggal responden dalam cakupan survei SNLIK 2022.', 'comparison_basis' => 'Selisih dua indeks pada survei yang sama, dalam poin persentase.'],
            ['code' => 'inclusion', 'label' => 'Inklusi keuangan', 'unit' => '%', 'available' => true, 'reason' => 'Arsip SNLIK OJK 2022 tersedia untuk 34 wilayah.', 'definition' => 'Indeks inklusi berdasarkan penggunaan produk dan layanan keuangan menurut SNLIK 2022.', 'location_basis' => 'Provinsi tempat tinggal responden dalam cakupan survei SNLIK 2022.', 'comparison_basis' => 'Selisih dua indeks pada survei yang sama, dalam poin persentase.'],
        ];
        $survey = array_map(fn (array $item): array => $item + ['format' => 'percent', 'periods' => [['id' => '2022', 'label' => 'SNLIK 2022']], 'default_period' => '2022', 'geography' => $this->regions->geography() + ['map_available' => true], 'regions' => $this->regions->regions()], $survey);
        $banking = [];
        foreach (['credit', 'deposits'] as $code) {
            $periods = $this->bi->periods($code);
            $banking[] = [
                'code' => $code, 'label' => $code === 'credit' ? 'Kredit per provinsi' : 'Simpanan per provinsi',
                'unit' => 'Rp miliar', 'source_unit' => 'Rp miliar', 'format' => 'currency', 'display_unit' => 'Rp triliun', 'display_scale' => 1000,
                'available' => count($periods) > 0, 'periods' => $periods, 'default_period' => $periods[0]['id'] ?? '2026-08',
                'geography' => $this->biRegions->geography(), 'regions' => $this->biRegions->regions(),
                'reason' => count($periods) ? 'Dua tabel BI edisi September 2026 telah diverifikasi dan diterbitkan.' : 'Data tabel BI belum diterbitkan atau provenance versi belum lengkap. Data yang belum tersedia tidak dihitung sebagai nol.',
                'definition' => $code === 'credit' ? 'Posisi akhir bulan pinjaman/kredit rupiah dan valuta asing bank umum dan BPR menurut lokasi proyek, Tabel I.12 SEKI BI.' : 'Posisi akhir bulan simpanan masyarakat rupiah dan valuta asing bank umum dan BPR menurut provinsi, Tabel I.19 SEKI BI. Mencakup giro, tabungan dan simpanan berjangka sesuai definisi sumber.',
                'location_basis' => $code === 'credit' ? 'Provinsi lokasi proyek atau penggunaan kredit. Lokasinya dapat berbeda dari kantor bank penyalur.' : 'Lokasi kantor bank penghimpun dana/pelapor. Angka ini bukan jumlah kekayaan atau simpanan berdasarkan tempat tinggal penduduk provinsi.',
                'comparison_basis' => 'Selisih posisi nominal dua provinsi pada akhir bulan yang sama. Kredit dan simpanan memakai dasar lokasi berbeda; keduanya tidak dipakai menghitung LDR provinsi pada halaman ini.',
            ];
        }

        return array_merge($survey, $banking);
    }

    public function regionsFor(string $indicator): array
    {
        return in_array($indicator, ['credit', 'deposits'], true) ? $this->biRegions->regions() : $this->regions->regions();
    }

    public function geographyFor(string $indicator): array
    {
        return in_array($indicator, ['credit', 'deposits'], true) ? $this->biRegions->geography() : $this->regions->geography();
    }

    public function defaultPeriod(string $indicator): string
    {
        return in_array($indicator, ['credit', 'deposits'], true) ? ($this->bi->periods($indicator)[0]['id'] ?? '2026-08') : '2022';
    }

    public function catalog(): array
    {
        return [
            'catalog_version' => 'province-comparison-2.0',
            'geography' => $this->regions->geography(),
            'regions' => $this->regions->regions(),
            'periods' => [['id' => '2022', 'label' => 'SNLIK 2022']],
            'indicators' => $this->indicators(),
            'rules' => $this->comparability->rules(),
            'sources' => $this->sources(),
        ];
    }

    public function compare(string $a, string $b, string $period, string $indicatorCode): array
    {
        $indicator = collect($this->indicators())->firstWhere('code', $indicatorCode);
        $banking = in_array($indicatorCode, ['credit', 'deposits'], true);
        $first = $banking ? $this->bi->observation($a, $period, $indicatorCode) : $this->observation($a, $period, $indicator);
        $second = $banking ? $this->bi->observation($b, $period, $indicatorCode) : $this->observation($b, $period, $indicator);
        $comparison = $this->comparability->evaluate($first, $second);
        if (! $indicator['available']) {
            array_unshift($comparison['reasons'], $indicator['reason']);
        } elseif ((! $banking && $period !== '2022') || ($banking && ($first['value'] === null || $second['value'] === null))) {
            array_unshift($comparison['reasons'], 'Belum ada angka provinsi untuk periode '.$period.' dalam arsip yang sudah diverifikasi.');
        }

        return [
            'geography' => $this->geographyFor($indicatorCode),
            'indicator' => $indicator,
            'period' => ['id' => $period, 'label' => strlen($period) === 7 ? CarbonImmutable::parse($period.'-01')->locale('id')->translatedFormat('F Y') : ($period === '2022' && ! $banking ? 'SNLIK 2022' : 'Tahun '.$period)],
            'a' => $first,
            'b' => $second,
            'comparison' => $comparison,
            'trend' => ['available' => false, 'reason' => $banking ? 'Arsip BI memuat posisi bulanan Januari 2025 hingga Agustus 2026. Perbandingan ini menampilkan satu bulan pilihan; tren antarbulan belum ditampilkan sebagai grafik.' : 'Baru ada satu periode provinsi yang diverifikasi. Tren memerlukan beberapa periode dengan definisi dan wilayah yang sebanding.'],
            'sources' => $banking ? $this->bi->sources($indicatorCode) : $this->sources(),
        ];
    }

    private function observation(string $code, string $period, array $indicator): array
    {
        $value = $indicator['available'] && $period === '2022' ? $this->regions->value($code, $indicator['code']) : null;
        $ready = $indicator['available'] && $period === '2022';

        return [
            'region' => $this->regions->find($code),
            'value' => $value,
            'status' => ! $indicator['available'] ? 'not_connected' : ($value === null ? 'missing' : 'available'),
            'metadata' => [
                'indicator_code' => $indicator['code'],
                'definition_id' => $ready ? 'ojk-snlik-2022-'.$indicator['code'] : null,
                'unit_code' => $ready ? 'percent' : null,
                'display_unit' => $ready ? '%' : null,
                'period_id' => $period,
                'observation_window' => $ready ? 'Juli–September 2022' : null,
                'frequency' => $ready ? 'survey' : null,
                'geography_id' => RegionRegistry::GEOGRAPHY_ID,
                'code_scheme' => RegionRegistry::CODE_SCHEME,
                'scope_id' => $ready ? 'snlik-2022-residents-15-79-official-frame' : null,
                'scope' => $ready ? 'Responden usia 15–79 tahun dalam cakupan SNLIK OJK 2022.' : null,
                'location_basis_id' => $ready ? 'survey-residence' : null,
                'method_id' => $ready ? 'ojk-snlik-2022' : null,
                'measurement_basis_id' => $ready ? 'survey-index' : null,
                'dataset_version' => $ready ? $this->regions->archive()['version'] : null,
                'release_date' => $ready ? $this->regions->archive()['source_release_date'] : null,
            ],
        ];
    }

    private function sources(): array
    {
        return [
            ['label' => 'OJK | Booklet SNLIK 2022', 'url' => $this->regions->archive()['source_url'], 'role' => 'data', 'release_date' => $this->regions->archive()['source_release_date'], 'checked_on' => $this->regions->archive()['source_checked_on'], 'sha256' => $this->regions->archive()['source_pdf_sha256']],
            ['label' => 'BNPB InaRISK | Batas dan kode arsip KDPPUM', 'url' => $this->regions->archive()['code_source_url'], 'role' => 'geography'],
        ];
    }
}
