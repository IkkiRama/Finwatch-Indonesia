<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class OjkSpiAdapter
{
    public const ARCHIVE_SHA256 = '2324b4890eb1a062607f77e7e98fb2e6d444aa951e5fe6f9a4015e648c8f714b';

    public function parse(string $path, string $sourceUrl, ?string $retrievedAt = null): array
    {
        if (! hash_equals(self::ARCHIVE_SHA256, hash_file('sha256', $path))) {
            throw new \RuntimeException('Checksum arsip berbeda dari SPI Juni 2025 yang telah ditinjau. Gunakan impor manual atau tinjau profil parser baru.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Workbook SPI tidak valid.');
        }
        $total = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            $total += $entry['size'];
            if ($total > 30 * 1024 * 1024 || preg_match('/vbaProject|embeddings/i', $entry['name'])) {
                $zip->close();
                throw new \RuntimeException('SPI melampaui batas berkas atau mengandung konten aktif.');
            }
        }
        $zip->close();
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Kinerja_KBMI 1.16.-1.20.']);
        $book = $reader->load($path);
        $sheet = $book->getSheetByName('Kinerja_KBMI 1.16.-1.20.');
        if (! $sheet || ! str_contains((string) $sheet->getCell('A1')->getValue(), 'Kinerja Bank Umum') || (int) $sheet->getCell('N2')->getValue() !== 2025 || $sheet->getCell('S3')->getValue() !== 'Jun') {
            throw new \RuntimeException('Layout/edisi SPI tidak cocok dengan profil Juni 2025; gunakan pemetaan manual.');
        }
        $definitions = [4 => ['car', 'Capital Adequacy Ratio (CAR)', '%', 1], 10 => ['roa', 'Return on Assets (ROA)', '%', 1], 16 => ['nim', 'Net Interest Margin (NIM)', '%', 1], 19 => ['ldr', 'Loan to Deposits Ratio (LDR)', '%', 1], 20 => ['credit', 'Kredit pihak ketiga bukan bank', 'Rp triliun', 1000], 21 => ['deposits', 'Dana pihak ketiga', 'Rp triliun', 1000], 25 => ['assets', 'Total aset bank umum', 'Rp triliun', 1000]];
        $rows = [];
        $columns = range('G', 'S');
        foreach ($definitions as $rowNumber => [$code,$label,$unit,$divisor]) {
            foreach ($columns as $offset => $column) {
                $month = (new \DateTimeImmutable('2024-06-01'))->modify('+'.$offset.' months')->modify('last day of this month')->format('Y-m-d');
                $cell = $sheet->getCell($column.$rowNumber);
                $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
                if (! is_numeric($value)) {
                    throw new \RuntimeException('Nilai agregat SPI tidak numerik; pemetaan memerlukan review.');
                }
                $rows[] = ['indicator_code' => $code, 'label' => $label, 'period_date' => $month, 'value' => number_format((float) $value / $divisor, 8, '.', ''), 'unit' => $unit, 'frequency' => 'monthly', 'scope' => 'Indonesia', 'source_url' => $sourceUrl, 'release_date' => '2025-08-29', 'retrieved_at' => $retrievedAt ?? now()->toIso8601String(), 'dataset_version' => 'SPI-Juni-2025', 'methodology_note' => 'Agregat Bank Umum, Tabel 1.16 SPI Juni 2025; vintage historis. Nilai moneter sumber (Rp miliar) dibagi 1.000 menjadi Rp triliun; rasio dipertahankan. Data bukan publikasi paling baru tahun berjalan.', 'sector' => 'banking', 'segment' => 'aggregate'];
            }
        }
        $book->disconnectWorksheets();

        return $rows;
    }
}
