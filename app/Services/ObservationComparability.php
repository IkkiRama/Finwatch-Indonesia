<?php

namespace App\Services;

class ObservationComparability
{
    private const FIELDS = [
        'indicator_code' => ['indicator', 'Indikator sama', 'Kode indikator berbeda atau belum ditetapkan.'],
        'definition_id' => ['definition', 'Definisi sama', 'Definisi angka berbeda atau belum diverifikasi.'],
        'unit_code' => ['unit', 'Satuan sama', 'Satuan berbeda atau belum ditetapkan.'],
        'period_id' => ['period', 'Periode sama', 'Periode observasi berbeda atau belum dicatat.'],
        'frequency' => ['frequency', 'Frekuensi sama', 'Frekuensi pencatatan berbeda atau belum dicatat.'],
        'geography_id' => ['geography', 'Cakupan wilayah sama', 'Versi cakupan wilayah berbeda atau belum diverifikasi.'],
        'code_scheme' => ['code_scheme', 'Sistem kode wilayah sama', 'Sistem kode wilayah berbeda atau belum ditetapkan.'],
        'scope_id' => ['scope', 'Cakupan responden atau lembaga sama', 'Cakupan responden atau lembaga berbeda atau belum ditetapkan.'],
        'location_basis_id' => ['location', 'Dasar lokasi sama', 'Dasar lokasi pencatatan berbeda atau belum diverifikasi.'],
        'method_id' => ['method', 'Metode sama', 'Metode berbeda atau belum diverifikasi.'],
        'measurement_basis_id' => ['measurement_basis', 'Dasar pengukuran sama', 'Dasar pengukuran, misalnya posisi akhir periode atau arus, berbeda atau belum ditetapkan.'],
        'dataset_version' => ['edition', 'Edisi sumber sama', 'Edisi atau revisi sumber berbeda atau belum ditetapkan.'],
    ];

    public function rules(): array
    {
        return array_merge([
            ['id' => 'availability', 'label' => 'Dua angka tersedia', 'description' => 'Nilai yang belum tersedia tetap kosong. Nol merupakan angka yang sah bila sumber memang mencatat nol.'],
        ], array_map(fn (array $field): array => ['id' => $field[0], 'label' => $field[1], 'description' => $field[2]], array_values(self::FIELDS)));
    }

    public function evaluate(array $a, array $b): array
    {
        $available = $this->available($a) && $this->available($b);
        $checks = [['id' => 'availability', 'label' => 'Dua angka tersedia', 'passed' => $available]];
        $reasons = $available ? [] : ['Satu atau kedua angka belum tersedia; nilai kosong tidak dihitung sebagai nol.'];
        foreach (self::FIELDS as $key => [$id, $label, $reason]) {
            $first = $a['metadata'][$key] ?? null;
            $second = $b['metadata'][$key] ?? null;
            $known = is_string($first) && trim($first) !== '' && is_string($second) && trim($second) !== '';
            $passed = $known ? $first === $second : null;
            $checks[] = ['id' => $id, 'label' => $label, 'passed' => $passed];
            if ($passed !== true) {
                $reasons[] = $reason;
            }
        }
        $comparable = ! array_filter($checks, fn (array $check): bool => $check['passed'] !== true);
        $difference = $comparable ? round((float) $a['value'] - (float) $b['value'], 8) : null;
        if ($difference !== null && ! is_finite($difference)) {
            $comparable = false;
            $difference = null;
            $reasons[] = 'Selisih melampaui rentang angka yang didukung.';
        }

        return [
            'status' => $comparable ? 'comparable' : 'unavailable',
            'difference' => $difference,
            'difference_unit' => $comparable ? ($a['metadata']['unit_code'] === 'percent' ? 'pp' : ($a['metadata']['display_unit'] ?? null)) : null,
            'difference_formula' => 'A − B',
            'reasons' => array_values(array_unique($reasons)),
            'checks' => $checks,
        ];
    }

    private function available(array $observation): bool
    {
        $value = $observation['value'] ?? null;

        return ($observation['status'] ?? null) === 'available' && is_numeric($value) && is_finite((float) $value);
    }
}
