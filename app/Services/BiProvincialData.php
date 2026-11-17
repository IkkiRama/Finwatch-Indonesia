<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\DataSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

class BiProvincialData
{
    private bool $loaded = false;

    private ?array $snapshot = null;

    public function __construct(private BiProvinceRegistry $regions) {}

    public function periods(string $indicator): array
    {
        $snapshot = $this->published();
        if (! $snapshot) {
            return [];
        }
        $periods = array_keys($snapshot['values'][$indicator] ?? []);
        rsort($periods, SORT_STRING);

        return array_map(fn (string $id): array => ['id' => $id, 'label' => CarbonImmutable::parse($id.'-01')->locale('id')->translatedFormat('F Y')], $periods);
    }

    public function observation(string $code, string $period, string $indicator): array
    {
        $snapshot = $this->published();
        $value = $snapshot['values'][$indicator][$period][$code] ?? null;

        return [
            'region' => $this->regions->find($code),
            'value' => $value,
            'status' => $snapshot === null ? 'not_connected' : ($value === null ? 'missing' : 'available'),
            'metadata' => [
                'indicator_code' => $indicator,
                'definition_id' => 'bi-seki-2026-09-'.$indicator,
                'unit_code' => 'idr-billion',
                'display_unit' => 'Rp miliar',
                'period_id' => $period,
                'frequency' => 'monthly',
                'geography_id' => BiProvinceRegistry::GEOGRAPHY_ID,
                'code_scheme' => BiProvinceRegistry::CODE_SCHEME,
                'scope_id' => 'bi-seki-commercial-rural-resident-noncentral-government',
                'scope' => 'Bank umum dan BPR; rupiah dan valuta asing dalam ekuivalen rupiah, mengikuti cakupan SEKI.',
                'location_basis_id' => $indicator === 'credit' ? 'bi-project-location' : 'bi-reporting-bank-office-location',
                'method_id' => 'bi-seki-2026-09-'.$indicator,
                'measurement_basis_id' => 'end-month-outstanding-position',
                'dataset_version' => $snapshot['version'] ?? null,
                'release_date' => $snapshot ? BiSekiProvincialAdapter::RELEASE_DATE : null,
                'retrieved_at' => $snapshot['manifest']['retrieved_at'] ?? null,
            ],
        ];
    }

    public function sources(string $indicator): array
    {
        $snapshot = $this->published();
        $profile = BiSekiProvincialAdapter::SOURCES[$indicator];
        $file = collect($snapshot['manifest']['files'] ?? [])->firstWhere('table', $profile['table']);

        return [
            ['label' => 'BI | SEKI '.$profile['table'].' (XLS sumber)', 'url' => $profile['url'], 'role' => 'data', 'publication_url' => BiSekiProvincialAdapter::PUBLICATION_URL, 'release_date' => $snapshot ? BiSekiProvincialAdapter::RELEASE_DATE : null, 'file_last_modified' => $file['last_modified'] ?? null, 'retrieved_at' => $snapshot['manifest']['retrieved_at'] ?? null, 'sha256' => $file['sha256'] ?? null],
            ['label' => 'BI | Publikasi SEKI September 2026, 15 Oktober 2026', 'url' => BiSekiProvincialAdapter::PUBLICATION_URL, 'role' => 'publication'],
            ['label' => 'BI | Metadata '.($indicator === 'credit' ? 'pinjaman yang diberikan' : 'simpanan masyarakat'), 'url' => 'https://www.bi.go.id/id/statistik/Metadata/SEKI/Documents/'.($indicator === 'credit' ? '05_Pinjaman-yang-Diberikan.pdf' : '04_Simpanan-Masyarakat.pdf'), 'role' => 'methodology'],
            ...($indicator === 'deposits' ? [['label' => 'BI | Penjelasan dasar lokasi kantor penghimpun dana', 'url' => 'https://www.bi.go.id/id/statistik/Metadata/SEKDA/Documents/SEKDA-Simpanan-Masyarakat.pdf', 'role' => 'location_methodology']] : []),
        ];
    }

    private function published(): ?array
    {
        if ($this->loaded) {
            return $this->snapshot;
        }
        $this->loaded = true;
        $sourceId = DataSource::where('code', 'BI')->value('id');
        $dataset = Dataset::where('code', BiSekiProvincialAdapter::DATASET_CODE)->where('data_source_id', $sourceId)->where('status', 'published')->where('published_at', '<=', now())->first();
        if (! $dataset?->current_version_id) {
            return null;
        }
        $version = DatasetVersion::whereKey($dataset->current_version_id)->where('dataset_id', $dataset->id)->where('status', 'published')->where('published_at', '<=', now())->first();
        if (! $version || $version->issues || ! $version->rows || ! Storage::disk('local')->exists($version->raw_path)) {
            return null;
        }
        $rawManifest = Storage::disk('local')->get($version->raw_path);
        if (strlen($rawManifest) > 100000) {
            return null;
        }
        $manifest = json_decode($rawManifest, true);
        if (! is_array($manifest) || ($manifest['profile'] ?? null) !== BiSekiProvincialAdapter::PROFILE || ($manifest['geography_id'] ?? null) !== BiProvinceRegistry::GEOGRAPHY_ID || ($manifest['release_date'] ?? null) !== BiSekiProvincialAdapter::RELEASE_DATE || ($manifest['publication_url'] ?? null) !== BiSekiProvincialAdapter::PUBLICATION_URL) {
            return null;
        }
        $expectedChecksum = hash('sha256', BiSekiProvincialAdapter::SOURCES['credit']['sha256'].':'.BiSekiProvincialAdapter::SOURCES['deposits']['sha256']);
        $expectedEdition = BiSekiProvincialAdapter::EDITION_ID.'-'.substr($expectedChecksum, 0, 12);
        if (! is_array($manifest['files'] ?? null) || count($manifest['files']) !== 2 || count(array_filter($manifest['files'], 'is_array')) !== 2 || ($manifest['checksum'] ?? null) !== $expectedChecksum || ($manifest['dataset_version'] ?? null) !== $expectedEdition) {
            return null;
        }
        foreach (BiSekiProvincialAdapter::SOURCES as $profile) {
            $files = array_values(array_filter($manifest['files'] ?? [], fn (array $file): bool => ($file['table'] ?? null) === $profile['table']));
            if (count($files) !== 1 || ($files[0]['url'] ?? null) !== $profile['url'] || ! is_string($files[0]['sha256'] ?? null) || ! hash_equals(strtolower($profile['sha256']), strtolower($files[0]['sha256']))) {
                return null;
            }
        }
        $values = [];
        $edition = null;
        $profileByCode = array_column(array_map(fn (string $code, array $profile): array => $profile + ['comparison_code' => $code], array_keys(BiSekiProvincialAdapter::SOURCES), array_values(BiSekiProvincialAdapter::SOURCES)), null, 'indicator_code');
        foreach ($version->rows as $row) {
            if (! is_array($row)) {
                return null;
            }
            $profile = $profileByCode[$row['indicator_code'] ?? ''] ?? null;
            $period = substr($row['period_date'] ?? '', 0, 7);
            $code = $row['scope'] ?? '';
            $value = $row['value'] ?? null;
            if (! $profile || ! $this->regions->find($code) || ! preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $period) || ($row['unit'] ?? null) !== 'Rp miliar' || ($row['frequency'] ?? null) !== 'monthly' || ($row['source_url'] ?? null) !== $profile['url'] || ($row['release_date'] ?? null) !== BiSekiProvincialAdapter::RELEASE_DATE || ! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
                return null;
            }
            $rowEdition = $row['dataset_version'] ?? null;
            if ($rowEdition !== $expectedEdition || ($edition !== null && $edition !== $rowEdition) || isset($values[$profile['comparison_code']][$period][$code])) {
                return null;
            }
            $edition = $rowEdition;
            $values[$profile['comparison_code']][$period][$code] = (float) $value;
        }
        foreach (['credit', 'deposits'] as $indicator) {
            if (empty($values[$indicator])) {
                return null;
            }
            foreach ($values[$indicator] as $periodValues) {
                if (count($periodValues) !== 34) {
                    return null;
                }
            }
        }
        if (array_keys($values['credit']) !== array_keys($values['deposits'])) {
            return null;
        }

        return $this->snapshot = ['values' => $values, 'version' => $edition, 'manifest' => $manifest];
    }
}
