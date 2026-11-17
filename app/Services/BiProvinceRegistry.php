<?php

namespace App\Services;

use RuntimeException;

class BiProvinceRegistry
{
    public const GEOGRAPHY_ID = 'bi-seki-provincial-34-v1';

    public const CODE_SCHEME = 'bi-seki-row-v1';

    private ?array $registry = null;

    public function geography(): array
    {
        $registry = $this->archive();

        return [
            'id' => self::GEOGRAPHY_ID,
            'label' => '34 wilayah dalam tabel SEKI BI',
            'code_scheme' => self::CODE_SCHEME,
            'code_scheme_label' => 'Identitas baris publikasi SEKI BI, bukan kode administratif',
            'boundary_note' => $registry['boundary_note'],
            'mapping_status' => 'verified_source_rows',
            'map_available' => false,
            'boundary_equivalence_verified' => false,
            'crosswalk_note' => $registry['crosswalk_note'],
            'source_url' => $registry['source_page_url'],
            'source_tables' => $registry['source_tables'],
            'verified_on' => $registry['verified_on'],
            'version' => $registry['version'],
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

    public function matchSource(int $ordinal, string $label): array
    {
        foreach ($this->archive()['rows'] as $row) {
            if ($row['source_row'] !== $ordinal) {
                continue;
            }
            $accepted = array_merge([$row['source_name']], $row['source_aliases']);
            $normalized = $this->normalizeLabel($label);
            foreach ($accepted as $expected) {
                if ($normalized === $this->normalizeLabel($expected)) {
                    return $this->identity($row);
                }
            }

            throw new RuntimeException('Nama wilayah pada baris BI '.$ordinal.' tidak cocok dengan registry yang diverifikasi.');
        }

        throw new RuntimeException('Nomor baris wilayah BI '.$ordinal.' belum dikenali. Total dan memo bukan provinsi.');
    }

    private function archive(): array
    {
        if ($this->registry !== null) {
            return $this->registry;
        }
        $path = database_path('sources/bi-seki-provincial-regions-v1.json');
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Registry wilayah BI tidak dapat dibaca.');
        }
        $registry = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (($registry['geography_id'] ?? null) !== self::GEOGRAPHY_ID
            || ($registry['code_scheme'] ?? null) !== self::CODE_SCHEME
            || ($registry['version'] ?? null) !== 'bi-seki-provincial-regions-v1'
            || ! is_array($registry['rows'] ?? null)
            || count($registry['rows']) !== 34) {
            throw new RuntimeException('Versi atau cakupan registry wilayah BI tidak cocok.');
        }
        $names = [];
        $crosswalk = [];
        foreach ($registry['rows'] as $index => $row) {
            $ordinal = $index + 1;
            if (($row['source_row'] ?? null) !== $ordinal
                || ($row['code'] ?? null) !== 'row-'.$ordinal
                || ! is_string($row['source_name'] ?? null)
                || trim($row['source_name']) === ''
                || ! is_string($row['name'] ?? null)
                || trim($row['name']) === ''
                || ! is_array($row['source_aliases'] ?? null)
                || ! array_key_exists('snlik_code', $row)) {
                throw new RuntimeException('Identitas baris registry wilayah BI tidak valid.');
            }
            $normalizedName = $this->normalizeLabel($row['source_name']);
            if (isset($names[$normalizedName])) {
                throw new RuntimeException('Nama wilayah BI tercatat lebih dari satu kali.');
            }
            $names[$normalizedName] = true;
            foreach ($row['source_aliases'] as $alias) {
                if (! is_string($alias) || trim($alias) === '') {
                    throw new RuntimeException('Alias wilayah BI tidak valid.');
                }
            }
            if (in_array($ordinal, [30, 31], true)) {
                if ($row['snlik_code'] !== null) {
                    throw new RuntimeException('Pemetaan batas Papua lintas sumber belum diverifikasi.');
                }
            } elseif (! is_string($row['snlik_code']) || ! preg_match('/^\d{2}$/', $row['snlik_code']) || isset($crosswalk[$row['snlik_code']])) {
                throw new RuntimeException('Pemetaan pilihan wilayah BI ke SNLIK tidak valid.');
            } else {
                $crosswalk[$row['snlik_code']] = true;
            }
        }
        if (count($crosswalk) !== 32) {
            throw new RuntimeException('Pemetaan nama wilayah BI belum lengkap.');
        }
        $this->registry = $registry;

        return $this->registry;
    }

    private function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $label)));
    }

    private function identity(array $row): array
    {
        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'source_row' => $row['source_row'],
            'source_name' => $row['source_name'],
            'snlik_code' => $row['snlik_code'],
            'code_scheme' => self::CODE_SCHEME,
            'geography_id' => self::GEOGRAPHY_ID,
            'canonical_id' => self::CODE_SCHEME.':'.self::GEOGRAPHY_ID.':'.$row['code'],
        ];
    }
}
