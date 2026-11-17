<?php

namespace Tests\Unit;

use App\Services\BiProvinceRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BiProvinceRegistryTest extends TestCase
{
    public function test_bi_row_ids_use_a_distinct_versioned_namespace(): void
    {
        $registry = new BiProvinceRegistry;
        $geography = $registry->geography();
        $regions = $registry->regions();

        $this->assertSame('bi-seki-provincial-34-v1', $geography['id']);
        $this->assertSame('bi-seki-row-v1', $geography['code_scheme']);
        $this->assertSame('2026-11-04', $geography['verified_on']);
        $this->assertSame(['I.12', 'I.19'], array_column($geography['source_tables'], 'id'));
        $this->assertFalse($geography['map_available']);
        $this->assertFalse($geography['boundary_equivalence_verified']);
        $this->assertCount(34, $regions);
        $this->assertSame(range(1, 34), array_column($regions, 'source_row'));
        $this->assertCount(34, array_unique(array_column($regions, 'code')));
        $this->assertCount(34, array_unique(array_column($regions, 'source_name')));
        $this->assertSame('Jawa Tengah', $registry->find('row-13')['name']);
        $this->assertSame('bi-seki-row-v1:bi-seki-provincial-34-v1:row-13', $registry->find('row-13')['canonical_id']);
    }

    public function test_selection_crosswalk_matches_32_names_without_mapping_papua_boundaries(): void
    {
        $registry = new BiProvinceRegistry;
        $survey = json_decode(file_get_contents(database_path('sources/snlik-provincial-2022.json')), true, 512, JSON_THROW_ON_ERROR);
        $surveyNames = array_column($survey['rows'], 'name', 'code');
        $expected = [
            1 => '11', 2 => '12', 3 => '13', 4 => '14', 5 => '15', 6 => '16', 7 => '19', 8 => '17',
            9 => '18', 10 => '36', 11 => '31', 12 => '32', 13 => '33', 14 => '34', 15 => '35',
            16 => '51', 17 => '52', 18 => '53', 19 => '61', 20 => '62', 21 => '63', 22 => '64',
            23 => '71', 24 => '75', 25 => '72', 26 => '73', 27 => '74', 28 => '82', 29 => '81',
            32 => '76', 33 => '21', 34 => '65',
        ];
        foreach ($expected as $ordinal => $surveyCode) {
            $region = $registry->find('row-'.$ordinal);
            $this->assertSame($surveyCode, $region['snlik_code']);
            $this->assertSame($surveyNames[$surveyCode], $region['name']);
        }
        $this->assertSame('Papua', $registry->find('row-30')['name']);
        $this->assertNull($registry->find('row-30')['snlik_code']);
        $this->assertSame('Papua Barat', $registry->find('row-31')['name']);
        $this->assertNull($registry->find('row-31')['snlik_code']);
        $this->assertStringContainsString('belum diverifikasi', $registry->geography()['boundary_note']);
    }

    public function test_all_verified_source_labels_match_their_own_row_and_preserve_provenance(): void
    {
        $registry = new BiProvinceRegistry;
        foreach ($registry->regions() as $region) {
            $this->assertSame($region, $registry->matchSource($region['source_row'], $region['source_name']));
        }
        $this->assertSame('Nanggroe Aceh Darussalam', $registry->matchSource(1, '  NANGGROE   ACEH DARUSSALAM ')['source_name']);
        $this->assertSame('Kepulauan Bangka Belitung', $registry->matchSource(7, 'Bangka Belitung')['name']);
        $this->assertSame('DKI Jakarta', $registry->matchSource(11, 'D.K.I. Jakarta')['name']);
        $this->assertSame('DI Yogyakarta', $registry->matchSource(14, 'D.I. Yogyakarta')['name']);
        foreach (['Nusa Tenggara Timur 1)', 'Nusa Tenggara Timur1)', 'Nusa Tenggara Timur'] as $label) {
            $matched = $registry->matchSource(18, $label);
            $this->assertSame('row-18', $matched['code']);
            $this->assertSame('Nusa Tenggara Timur 1)', $matched['source_name']);
        }
    }

    #[DataProvider('unverifiedSourceRows')]
    public function test_unknown_or_mismatched_source_rows_fail_closed(int $ordinal, string $label): void
    {
        $this->expectException(RuntimeException::class);
        (new BiProvinceRegistry)->matchSource($ordinal, $label);
    }

    public static function unverifiedSourceRows(): array
    {
        return [
            'unknown row' => [0, 'Aceh'],
            'total is not province' => [35, 'Jumlah'],
            'memo is not province' => [36, 'Memo Items'],
            'government memo is not province' => [37, 'Pemerintah Pusat'],
            'nonresident memo is not province' => [38, 'Bukan Penduduk'],
            'swapped name' => [13, 'Jawa Barat'],
            'numeric code is not name' => [13, '33'],
            'wrong papua row' => [31, 'Papua'],
            'unlisted split province' => [30, 'Papua Tengah'],
            'unapproved renamed source label' => [1, 'Aceh'],
            'unexpected footnote' => [18, 'Nusa Tenggara Timur 2)'],
        ];
    }

    public function test_numeric_codes_and_unlisted_new_provinces_are_not_reinterpreted(): void
    {
        $registry = new BiProvinceRegistry;
        foreach (['33', '91', '92', '94', 'row-0', 'row-35', 'row-95', 'row-013', 'Papua Tengah'] as $code) {
            $this->assertNull($registry->find($code));
        }
        $this->assertNotContains('Papua Tengah', array_column($registry->regions(), 'name'));
        $this->assertNotContains('Papua Selatan', array_column($registry->regions(), 'name'));
        $this->assertNotContains('Papua Pegunungan', array_column($registry->regions(), 'name'));
        $this->assertNotContains('Papua Barat Daya', array_column($registry->regions(), 'name'));
    }
}
