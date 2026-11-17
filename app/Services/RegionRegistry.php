<?php

namespace App\Services;

use RuntimeException;

class RegionRegistry
{
    public const GEOGRAPHY_ID = 'snlik-2022-34';

    public const CODE_SCHEME = 'bnpb-kdppum';

    private ?array $archive = null;

    public function archive(): array
    {
        if ($this->archive === null) {
            $this->archive = json_decode(file_get_contents(database_path('sources/snlik-provincial-2022.json')), true, 512, JSON_THROW_ON_ERROR);
            if ($this->archive['geography_id'] !== self::GEOGRAPHY_ID || $this->archive['code_scheme'] !== self::CODE_SCHEME || count($this->archive['rows']) !== 34) {
                throw new RuntimeException('Arsip wilayah tidak cocok dengan registry yang diverifikasi.');
            }
        }

        return $this->archive;
    }

    public function geography(): array
    {
        return [
            'id' => self::GEOGRAPHY_ID,
            'label' => 'Cakupan SNLIK 2022, 34 wilayah survei',
            'code_scheme' => self::CODE_SCHEME,
            'code_scheme_label' => 'Kode arsip BNPB, kolom KDPPUM',
            'boundary_note' => 'Papua dan Papua Barat mengikuti cakupan survei sebelum pemekaran. Nilai tidak dibagi ke provinsi baru. Kode KDPPUM tidak dianggap sama dengan kode BPS atau BI.',
            'mapping_status' => 'archive_only',
            'bi_crosswalk_verified' => false,
            'source_url' => $this->archive()['code_source_url'],
        ];
    }

    public function regions(): array
    {
        return array_map(fn (array $row): array => $this->identity($row), $this->archive()['rows']);
    }

    public function find(string $code): ?array
    {
        foreach ($this->archive()['rows'] as $row) {
            if ($row['code'] === $code) {
                return $this->identity($row);
            }
        }

        return null;
    }

    public function resolve(string $value, string $codeScheme, string $geographyId): ?array
    {
        if ($codeScheme !== self::CODE_SCHEME || $geographyId !== self::GEOGRAPHY_ID) {
            return null;
        }
        $term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
        foreach ($this->regions() as $region) {
            if ($region['code'] === $term || mb_strtolower($region['name']) === $term) {
                return $region;
            }
        }

        return null;
    }

    public function value(string $code, string $indicator): ?float
    {
        if (! in_array($indicator, ['literacy', 'inclusion'], true)) {
            return null;
        }
        foreach ($this->archive()['rows'] as $row) {
            if ($row['code'] === $code) {
                $value = $row[$indicator] ?? null;

                return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0 && (float) $value <= 100 ? (float) $value : null;
            }
        }

        return null;
    }

    private function identity(array $row): array
    {
        return ['code' => $row['code'], 'name' => $row['name'], 'code_scheme' => self::CODE_SCHEME, 'geography_id' => self::GEOGRAPHY_ID, 'canonical_id' => self::CODE_SCHEME.':'.self::GEOGRAPHY_ID.':'.$row['code']];
    }
}
