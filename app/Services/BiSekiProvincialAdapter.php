<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class BiSekiProvincialAdapter
{
    public const PROFILE = 'bi_seki_provincial_v1';

    public const DATASET_CODE = 'bi-seki-provincial';

    public const EDITION_ID = 'SEKI-September-2026';

    public const RELEASE_DATE = '2026-10-15';

    public const PUBLICATION_URL = 'https://www.bi.go.id/id/statistik/ekonomi-keuangan/seki/Pages/SEKI-SEPTEMBER-2026.aspx';

    public const SOURCES = [
        'credit' => [
            'table' => 'I.12', 'sheet' => '1.12', 'indicator_code' => 'bi_credit_provincial',
            'label' => 'Kredit rupiah dan valas menurut lokasi proyek',
            'url' => 'https://www.bi.go.id/SEKI/tabel/TABEL1_12.xls',
            'sha256' => 'b104d0c38900c9b52018f54fe734f25ff19842365a8376cc36bcb6ff95ca2a50',
            'title' => 'I.12 POSISI PINJAMAN/KREDIT YANG DIBERIKAN RUPIAH DAN VALAS BANK UMUM DAN BPR MENURUT LOKASI PROYEK PROVINSI',
        ],
        'deposits' => [
            'table' => 'I.19', 'sheet' => '1.19', 'indicator_code' => 'bi_deposits_provincial',
            'label' => 'Simpanan masyarakat rupiah dan valas menurut provinsi',
            'url' => 'https://www.bi.go.id/SEKI/tabel/TABEL1_19.xls',
            'sha256' => '67bfc9e3148ce307c6d9291f0497b63c09f2263347497a5b14a1d611e2df0f9f',
            'title' => 'I.19 POSISI SIMPANAN MASYARAKAT RUPIAH DAN VALAS BANK UMUM DAN BPR MENURUT PROVINSI',
        ],
    ];

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    public function __construct(private BiProvinceRegistry $regions) {}

    public function parse(string $path, string $kind, ?string $retrievedAt = null, ?string $datasetVersion = null): array
    {
        $source = self::SOURCES[$kind] ?? throw new RuntimeException('Tabel provinsi BI belum dikenali.');
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024 || ! hash_equals($source['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('Berkas BI berbeda dari edisi September 2026 yang telah diverifikasi. Tinjau profil edisi baru sebelum mengimpor.');
        }
        $handle = fopen($path, 'rb');
        $signature = fread($handle, 8);
        fclose($handle);
        if ($signature !== hex2bin('d0cf11e0a1b11ae1')) {
            throw new RuntimeException('Berkas BI bukan workbook XLS yang diverifikasi.');
        }
        $reader = IOFactory::createReader('Xls');
        $reader->setReadDataOnly(true);
        $info = collect($reader->listWorksheetInfo($path))->firstWhere('worksheetName', $source['sheet']);
        if (! $info || $info['totalRows'] > 100 || $info['totalColumns'] > 40) {
            throw new RuntimeException('Ukuran atau nama lembar tabel BI berubah; tinjau pemetaan terlebih dahulu.');
        }
        $reader->setLoadSheetsOnly([$source['sheet']]);
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheetByName($source['sheet']);
            if (! $sheet || $this->normalize((string) $sheet->getCell('A1')->getValue()) !== $source['title'] || $this->normalize((string) $sheet->getCell('A2')->getValue()) !== '(Miliar Rp)' || $sheet->getCell('C4')->getValue() !== 'PROVINSI') {
                throw new RuntimeException('Judul, satuan, atau struktur tabel BI tidak sesuai profil yang diverifikasi.');
            }
            $periods = $this->periods($sheet);
            $rows = [];
            $totals = array_fill_keys(array_keys($periods), 0.0);
            for ($ordinal = 1; $ordinal <= 34; $ordinal++) {
                $rowNumber = $ordinal + 5;
                if ($sheet->getCell('A'.$rowNumber)->getValue() !== $ordinal) {
                    throw new RuntimeException('Urutan baris provinsi BI berubah atau tidak lengkap.');
                }
                $region = $this->regions->matchSource($ordinal, (string) $sheet->getCell('C'.$rowNumber)->getValue());
                if (abs($this->number($sheet->getCell('P'.$rowNumber)) - $this->number($sheet->getCell('O'.$rowNumber))) > 0.00001) {
                    throw new RuntimeException('Kolom ringkasan tahunan BI berbeda dari posisi Desember. Tinjau profil sebelum mengimpor.');
                }
                foreach ($periods as $column => $period) {
                    $value = $this->number($sheet->getCell([$column, $rowNumber]));
                    $totals[$column] += $value;
                    $rows[] = [
                        'indicator_code' => $source['indicator_code'], 'label' => $source['label'],
                        'period_date' => $period, 'value' => number_format($value, 8, '.', ''),
                        'unit' => 'Rp miliar', 'frequency' => 'monthly', 'scope' => $region['code'],
                        'source_url' => $source['url'], 'release_date' => self::RELEASE_DATE,
                        'retrieved_at' => $retrievedAt ?? now()->toIso8601String(),
                        'dataset_version' => $datasetVersion ?? self::EDITION_ID,
                        'methodology_note' => $this->methodology($kind), 'sector' => 'banking', 'segment' => 'aggregate',
                    ];
                }
            }
            if ($sheet->getCell('A40')->getValue() !== 35 || $this->normalize((string) $sheet->getCell('C40')->getValue()) !== 'Jumlah') {
                throw new RuntimeException('Baris jumlah BI berubah; cakupan provinsi harus diperiksa ulang.');
            }
            foreach ($totals as $column => $total) {
                if (abs($total - $this->number($sheet->getCell([$column, 40]))) > 0.01) {
                    throw new RuntimeException('Jumlah provinsi tidak sesuai total sumber BI.');
                }
            }
            $this->validateFooter($sheet, $kind);

            return $rows;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function periods(Worksheet $sheet): array
    {
        $firstYear = $sheet->getCell('D4')->getValue();
        if (! is_int($firstYear) || $firstYear !== 2025 || $sheet->getCell('P5')->getValue() !== null) {
            throw new RuntimeException('Tahun atau pemisah periode BI berbeda dari profil September 2026.');
        }
        $periods = [];
        foreach (range(4, 15) as $column) {
            $month = $column - 3;
            if (trim((string) $sheet->getCell([$column, 5])->getValue()) !== self::MONTHS[$month - 1]) {
                throw new RuntimeException('Urutan bulan BI tidak lengkap atau duplikat.');
            }
            $periods[$column] = CarbonImmutable::create($firstYear, $month, 1)->endOfMonth()->format('Y-m-d');
        }
        if ($sheet->getCell('X4')->getValue() !== 2026) {
            throw new RuntimeException('Tahun periode terbaru BI berubah; profil perlu ditinjau.');
        }
        foreach (range(17, 24) as $column) {
            $month = $column - 16;
            if (trim((string) $sheet->getCell([$column, 5])->getValue()) !== self::MONTHS[$month - 1]) {
                throw new RuntimeException('Bulan periode terbaru BI tidak lengkap atau duplikat.');
            }
            $periods[$column] = CarbonImmutable::create(2026, $month, 1)->endOfMonth()->format('Y-m-d');
        }
        foreach ([25, 26] as $column) {
            if ($sheet->getCell([$column, 5])->getValue() !== null) {
                throw new RuntimeException('Ada kolom periode baru di luar profil yang diverifikasi.');
            }
        }

        return $periods;
    }

    private function number(Cell $cell): float
    {
        $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 999999999999999) {
            throw new RuntimeException('Angka BI kosong, negatif, atau tidak valid. Nilai kosong tidak diganti dengan nol.');
        }

        return (float) $value;
    }

    private function validateFooter(Worksheet $sheet, string $kind): void
    {
        if ($kind === 'deposits') {
            foreach ([41 => [36, ''], 42 => [37, 'Pemerintah Pusat'], 43 => [38, 'Bukan Penduduk']] as $row => [$ordinal, $label]) {
                if ($sheet->getCell('A'.$row)->getValue() !== $ordinal || $this->normalize((string) $sheet->getCell('C'.$row)->getValue()) !== $label) {
                    throw new RuntimeException('Baris memo BI berubah. Memo dan pemerintah pusat tidak boleh dibaca sebagai provinsi.');
                }
            }
        }
    }

    private function methodology(string $kind): string
    {
        $basis = $kind === 'credit'
            ? 'Kredit rupiah dan valas Bank Umum dan BPR menurut lokasi proyek, yaitu daerah penggunaan kredit sesuai metadata Pinjaman yang Diberikan BI.'
            : 'Giro, tabungan dan simpanan berjangka masyarakat rupiah dan valas Bank Umum dan BPR menurut provinsi. Dasar lokasi bank merujuk metadata simpanan BI; tidak disamakan dengan lokasi penggunaan kredit.';

        return $basis.' Posisi akhir bulan dalam Rp miliar, presisi XLS dipertahankan. Edisi SEKI September 2026 dirilis 15 Oktober 2026; bulan observasi terbaru Agustus 2026. Identitas row-1 sampai row-34 adalah baris sumber, bukan kode BPS/KDPPUM. Total dan memo dikecualikan. Nilai tidak dibagi ke provinsi Papua baru. Tidak digunakan untuk menghitung LDR antarprovinsi.';
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
