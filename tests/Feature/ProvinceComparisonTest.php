<?php

namespace Tests\Feature;

use App\Services\ObservationComparability;
use App\Services\RegionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvinceComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_versions_the_original_code_scheme_and_exposes_no_bi_crosswalk(): void
    {
        $catalog = $this->getJson('/api/v1/province-comparison/catalog')->assertOk()
            ->assertJsonPath('geography.id', 'snlik-2022-34')
            ->assertJsonPath('geography.code_scheme', 'bnpb-kdppum')
            ->assertJsonPath('geography.bi_crosswalk_verified', false)
            ->assertJsonCount(34, 'regions')->assertJsonCount(1, 'periods')->json();
        $regions = collect($catalog['regions'])->keyBy('code');
        $this->assertSame('Papua', $regions['91']['name']);
        $this->assertSame('Papua Barat', $regions['92']['name']);
        $this->assertSame('bnpb-kdppum:snlik-2022-34:91', $regions['91']['canonical_id']);
        $this->assertFalse(collect($catalog['indicators'])->firstWhere('code', 'credit')['available']);
        $this->assertFalse(collect($catalog['indicators'])->firstWhere('code', 'deposits')['available']);
        $this->assertSame('2022-11-24', $catalog['sources'][0]['release_date']);
    }

    public function test_verified_same_survey_provinces_are_compared_in_percentage_points(): void
    {
        $data = $this->getJson('/api/v1/province-comparison?a=33&b=32&period=2022&indicator=literacy')->assertOk()
            ->assertJsonPath('a.region.name', 'Jawa Tengah')->assertJsonPath('b.region.name', 'Jawa Barat')
            ->assertJsonPath('a.value', 51.69)->assertJsonPath('b.value', 56.1)
            ->assertJsonPath('comparison.status', 'comparable')->assertJsonPath('comparison.difference', -4.41)
            ->assertJsonPath('comparison.difference_unit', 'pp')->assertJsonPath('trend.available', false)->json();
        $this->assertSame([], $data['comparison']['reasons']);
        $this->assertNotContains(false, array_column($data['comparison']['checks'], 'passed'));
        $this->assertNotContains(null, array_column($data['comparison']['checks'], 'passed'));
        $this->getJson('/api/v1/province-comparison?a=32&b=33&period=2022&indicator=inclusion')->assertOk()
            ->assertJsonPath('comparison.difference', 2.34);
    }

    public function test_unconnected_bi_and_missing_survey_years_remain_null_instead_of_zero(): void
    {
        foreach (['credit', 'deposits'] as $code) {
            $response = $this->getJson('/api/v1/province-comparison?indicator='.$code)->assertOk()
                ->assertJsonPath('a.value', null)->assertJsonPath('b.value', null)
                ->assertJsonPath('a.status', 'not_connected')->assertJsonPath('comparison.status', 'unavailable')
                ->assertJsonPath('comparison.difference', null)->assertJsonPath('comparison.difference_unit', null);
            $this->assertNotEmpty($response->json('comparison.reasons'));
            $this->assertTrue(collect($response->json('comparison.checks'))->firstWhere('id', 'location')['passed']);
            $this->assertNull(collect($response->json('comparison.checks'))->firstWhere('id', 'edition')['passed']);
        }
        $this->getJson('/api/v1/province-comparison?period=2025&indicator=literacy')->assertOk()
            ->assertJsonPath('period.id', '2025')->assertJsonPath('a.status', 'missing')
            ->assertJsonPath('a.value', null)->assertJsonPath('comparison.difference', null);
    }

    public function test_unknown_regions_same_region_and_incompatible_code_schemes_are_rejected(): void
    {
        $this->getJson('/api/v1/province-comparison?a=94')->assertUnprocessable();
        $this->getJson('/api/v1/province-comparison?a=33&b=33')->assertUnprocessable();
        $this->getJson('/api/v1/province-comparison?indicator=nonsense')->assertUnprocessable();
        $this->getJson('/api/v1/province-comparison?period=2022-01')->assertUnprocessable();
        $this->getJson('/api/v1/province-comparison?code_scheme=bps')->assertUnprocessable();
        $this->getJson('/api/v1/province-comparison?geography=current')->assertUnprocessable();
        $registry = app(RegionRegistry::class);
        $this->assertSame('91', $registry->resolve('Papua', 'bnpb-kdppum', 'snlik-2022-34')['code']);
        $this->assertNull($registry->resolve('Papua', 'bps', 'snlik-2022-34'));
        $this->assertNull($registry->resolve('Papua Tengah', 'bnpb-kdppum', 'snlik-2022-34'));
    }

    private function observation(mixed $value, string $status = 'available'): array
    {
        return ['value' => $value, 'status' => $status, 'metadata' => [
            'indicator_code' => 'literacy', 'definition_id' => 'ojk-snlik-2022-literacy', 'unit_code' => 'percent',
            'display_unit' => '%', 'period_id' => '2022', 'frequency' => 'survey', 'geography_id' => 'snlik-2022-34',
            'code_scheme' => 'bnpb-kdppum', 'scope_id' => 'residents-15-79', 'location_basis_id' => 'survey-residence',
            'method_id' => 'ojk-snlik-2022', 'measurement_basis_id' => 'survey-index', 'dataset_version' => 'curated-v1',
        ]];
    }

    public function test_real_zero_is_comparable_but_missing_or_nonfinite_values_are_not(): void
    {
        $comparator = app(ObservationComparability::class);
        $zero = $comparator->evaluate($this->observation(0), $this->observation(1));
        $this->assertSame('comparable', $zero['status']);
        $this->assertSame(-1.0, $zero['difference']);
        foreach ([null, '', INF, NAN] as $missing) {
            $result = $comparator->evaluate($this->observation($missing), $this->observation(1));
            $this->assertSame('unavailable', $result['status']);
            $this->assertNull($result['difference']);
        }
        $this->assertSame('unavailable', $comparator->evaluate($this->observation(0, 'missing'), $this->observation(1))['status']);
    }

    public function test_each_metadata_mismatch_or_unknown_basis_blocks_comparison(): void
    {
        $comparator = app(ObservationComparability::class);
        $a = $this->observation(10);
        foreach (['indicator_code', 'definition_id', 'unit_code', 'period_id', 'frequency', 'geography_id', 'code_scheme', 'scope_id', 'location_basis_id', 'method_id', 'measurement_basis_id', 'dataset_version'] as $field) {
            $b = $this->observation(20);
            $b['metadata'][$field] = 'incompatible-definition';
            $result = $comparator->evaluate($a, $b);
            $this->assertSame('unavailable', $result['status'], $field);
            $this->assertNull($result['difference'], $field);
            $this->assertNotEmpty($result['reasons'], $field);
            $unknownA = $a;
            $unknownB = $b;
            $unknownA['metadata'][$field] = null;
            $unknownB['metadata'][$field] = null;
            $this->assertSame('unavailable', $comparator->evaluate($unknownA, $unknownB)['status'], $field.' unknown');
        }
    }
}
