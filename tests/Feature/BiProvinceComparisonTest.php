<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\DataSource;
use App\Services\BiProvinceRegistry;
use App\Services\BiSekiProvincialAdapter;
use App\Services\DatasetImporter;
use Carbon\CarbonImmutable;
use Database\Seeders\DataSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BiProvinceComparisonTest extends TestCase
{
    use RefreshDatabase;

    private const RETRIEVED_AT = '2026-11-04T00:00:00+00:00';

    private const MANIFEST_PATH = 'snapshots/bi-seki/api-comparison-manifest.json';

    private static ?array $parsedRows = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-04T08:00:00+07:00'));
        $this->seed(DataSourceSeeder::class);
        Storage::fake('local');
    }

    public function test_only_the_reviewed_published_version_enables_monthly_bi_choices(): void
    {
        $version = $this->stageBi();
        $catalog = $this->getJson('/api/v1/province-comparison/catalog')->assertOk()->json();
        $indicators = collect($catalog['indicators'])->keyBy('code');
        $this->assertFalse($indicators['credit']['available']);
        $this->assertFalse($indicators['deposits']['available']);
        $this->assertSame([], $indicators['credit']['periods']);
        $this->getJson('/api/v1/province-comparison?indicator=credit&a=row-13&b=row-12&period=2026-08')->assertOk()
            ->assertJsonPath('a.status', 'not_connected')->assertJsonPath('a.value', null)
            ->assertJsonPath('comparison.status', 'unavailable')->assertJsonPath('comparison.difference', null);

        app(DatasetImporter::class)->publish($version, null);
        $catalog = $this->getJson('/api/v1/province-comparison/catalog')->assertOk()
            ->assertJsonPath('geography.id', 'snlik-2022-34')->assertJsonCount(34, 'regions')->json();
        $indicators = collect($catalog['indicators'])->keyBy('code');
        foreach (['credit', 'deposits'] as $code) {
            $indicator = $indicators[$code];
            $this->assertTrue($indicator['available']);
            $this->assertSame('currency', $indicator['format']);
            $this->assertSame('Rp miliar', $indicator['unit']);
            $this->assertSame('Rp triliun', $indicator['display_unit']);
            $this->assertSame(1000, $indicator['display_scale']);
            $this->assertSame('2026-08', $indicator['default_period']);
            $this->assertCount(20, $indicator['periods']);
            $this->assertSame('2026-08', $indicator['periods'][0]['id']);
            $this->assertSame('2025-01', $indicator['periods'][19]['id']);
            $this->assertCount(34, $indicator['regions']);
            $this->assertSame(BiProvinceRegistry::GEOGRAPHY_ID, $indicator['geography']['id']);
            $this->assertSame(BiProvinceRegistry::CODE_SCHEME, $indicator['geography']['code_scheme']);
            $this->assertFalse($indicator['geography']['map_available']);
            $regions = collect($indicator['regions'])->keyBy('code');
            $this->assertSame('Jawa Tengah', $regions['row-13']['name']);
            $this->assertSame('33', $regions['row-13']['snlik_code']);
            $this->assertSame('Papua', $regions['row-30']['name']);
            $this->assertNull($regions['row-30']['snlik_code']);
            $this->assertArrayNotHasKey('row-35', $regions);
        }
        $this->assertSame([['id' => '2022', 'label' => 'SNLIK 2022']], $indicators['literacy']['periods']);
    }

    public function test_credit_and_deposits_keep_precise_source_values_units_and_different_location_bases(): void
    {
        $this->publishBi();
        foreach ([
            'credit' => [1126541.1526, 535521.7562, 591019.3964, 'bi-project-location'],
            'deposits' => [763752.4404, 489945.0455, 273807.3949, 'bi-reporting-bank-office-location'],
        ] as $code => [$westJava, $centralJava, $difference, $locationBasis]) {
            $data = $this->getJson('/api/v1/province-comparison?indicator='.$code.'&a=row-12&b=row-13&period=2026-08')->assertOk()
                ->assertJsonPath('period.id', '2026-08')->assertJsonPath('a.region.name', 'Jawa Barat')
                ->assertJsonPath('b.region.name', 'Jawa Tengah')->assertJsonPath('a.status', 'available')
                ->assertJsonPath('comparison.status', 'comparable')->assertJsonPath('comparison.difference_unit', 'Rp miliar')
                ->assertJsonPath('geography.id', BiProvinceRegistry::GEOGRAPHY_ID)->assertJsonPath('geography.map_available', false)
                ->assertJsonPath('a.metadata.location_basis_id', $locationBasis)->json();
            $this->assertEqualsWithDelta($westJava, $data['a']['value'], 0.0001);
            $this->assertEqualsWithDelta($centralJava, $data['b']['value'], 0.0001);
            $this->assertEqualsWithDelta($difference, $data['comparison']['difference'], 0.0001);
            $this->assertSame([], $data['comparison']['reasons']);
            $this->assertNotContains(false, array_column($data['comparison']['checks'], 'passed'));
            $this->assertNotContains(null, array_column($data['comparison']['checks'], 'passed'));
            $source = collect($data['sources'])->firstWhere('url', BiSekiProvincialAdapter::SOURCES[$code]['url']);
            $this->assertSame(BiSekiProvincialAdapter::RELEASE_DATE, $source['release_date']);
            $this->assertSame(self::RETRIEVED_AT, $source['retrieved_at']);
            $this->assertSame(BiSekiProvincialAdapter::SOURCES[$code]['sha256'], $source['sha256']);
            $this->assertStringStartsWith('2026-10-05', $source['file_last_modified']);
            $this->assertContains(BiSekiProvincialAdapter::PUBLICATION_URL, array_column($data['sources'], 'url'));
        }
        $this->getJson('/api/v1/province-comparison?indicator=credit&a=row-13&b=row-12')->assertOk()
            ->assertJsonPath('period.id', '2026-08')->assertJsonPath('a.region.name', 'Jawa Tengah');
    }

    public function test_missing_months_stay_missing_and_do_not_fall_back_to_the_latest_month(): void
    {
        $this->publishBi();
        foreach (['2024-12', '2026-09'] as $period) {
            $this->getJson('/api/v1/province-comparison?indicator=deposits&a=row-13&b=row-12&period='.$period)->assertOk()
                ->assertJsonPath('period.id', $period)->assertJsonPath('a.status', 'missing')
                ->assertJsonPath('a.value', null)->assertJsonPath('b.value', null)
                ->assertJsonPath('comparison.status', 'unavailable')->assertJsonPath('comparison.difference', null)
                ->assertJsonPath('comparison.difference_unit', null);
        }
        $this->getJson('/api/v1/province-comparison?indicator=literacy&a=33&b=32&period=2022')->assertOk()
            ->assertJsonPath('a.value', 51.69)->assertJsonPath('comparison.difference_unit', 'pp');
    }

    public function test_bi_row_ids_cannot_be_replaced_by_survey_codes_totals_or_unverified_provinces(): void
    {
        $this->publishBi();
        foreach ([
            'a=33&b=32',
            'a=row-13&b=row-13',
            'a=row-35&b=row-12',
            'a=row-38&b=row-12',
            'a=Papua%20Tengah&b=row-12',
            'a=row-13&b=row-12&code_scheme=bnpb-kdppum',
            'a=row-13&b=row-12&geography=snlik-2022-34',
            'a=row-13&b=row-12&period=2026-13',
        ] as $selection) {
            $this->getJson('/api/v1/province-comparison?indicator=credit&'.$selection)->assertUnprocessable();
        }
        $this->getJson('/api/v1/province-comparison?indicator=literacy&a=row-13&b=row-12')->assertUnprocessable();
    }

    public function test_a_missing_or_unverified_manifest_cannot_expose_a_published_bi_version(): void
    {
        $this->publishBi();
        $manifest = json_decode(Storage::disk('local')->get(self::MANIFEST_PATH), true, 512, JSON_THROW_ON_ERROR);
        $altered = $manifest;
        $altered['files'][0]['sha256'] = str_repeat('0', 64);
        Storage::disk('local')->put(self::MANIFEST_PATH, json_encode($altered, JSON_THROW_ON_ERROR));
        $catalog = $this->getJson('/api/v1/province-comparison/catalog')->assertOk()->json();
        $this->assertFalse(collect($catalog['indicators'])->firstWhere('code', 'credit')['available']);
        foreach (['files' => 'corrupt', 'dataset_version' => 'SEKI-September-2026-unverified', 'checksum' => 'wrong'] as $key => $value) {
            Storage::disk('local')->put(self::MANIFEST_PATH, json_encode(array_replace($manifest, [$key => $value]), JSON_THROW_ON_ERROR));
            $catalog = $this->getJson('/api/v1/province-comparison/catalog')->assertOk()->json();
            $this->assertFalse(collect($catalog['indicators'])->firstWhere('code', 'credit')['available']);
        }
        Storage::disk('local')->delete(self::MANIFEST_PATH);
        $this->getJson('/api/v1/province-comparison?indicator=credit&a=row-13&b=row-12&period=2026-08')->assertOk()
            ->assertJsonPath('a.value', null)->assertJsonPath('a.status', 'not_connected')
            ->assertJsonPath('comparison.difference', null);
    }

    public function test_provincial_series_do_not_mix_with_the_national_dashboard_and_explorer_names_the_province(): void
    {
        $this->publishBi();
        $national = Dataset::create(['code' => 'api-national-fixture', 'name' => 'National scope fixture', 'data_source_id' => DataSource::where('code', 'OJK')->value('id'), 'status' => 'draft']);
        $row = [
            'indicator_code' => 'credit', 'label' => 'Kredit nasional uji', 'period_date' => '2025-06-30',
            'value' => '8000.00000000', 'unit' => 'Rp triliun', 'frequency' => 'monthly', 'scope' => 'Indonesia',
            'source_url' => 'https://www.ojk.go.id/publication.csv', 'release_date' => '2025-08-29',
            'retrieved_at' => self::RETRIEVED_AT, 'dataset_version' => 'national-fixture',
            'methodology_note' => 'National test fixture.', 'sector' => 'banking', 'segment' => 'aggregate',
        ];
        $importer = app(DatasetImporter::class);
        $version = $importer->stage($national, [$row], 'snapshots/national-fixture.csv', null);
        $this->assertSame([], $version->issues);
        $importer->publish($version, null);
        $this->getJson('/api/v1/analytics?sector=banking')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'credit')->assertJsonPath('data.0.scope', 'Indonesia');
        $regional = $this->getJson('/api/v1/analytics?sector=banking&scope=row-13')->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame(['row-13'], array_values(array_unique(array_column($regional, 'scope'))));
        $observations = $this->getJson('/api/v1/observations?indicator=bi_credit_provincial&scope=row-13&year=2026&per_page=100')->assertOk()
            ->assertJsonCount(8, 'data')->assertJsonPath('data.0.scope_label', 'Jawa Tengah')
            ->assertJsonPath('data.0.period_date', '2026-08-31')->assertJsonPath('data.0.release_date', '2026-10-15')->json('data');
        $this->assertSame(['Rp miliar'], array_values(array_unique(array_column($observations, 'unit'))));
        $this->assertSame([BiSekiProvincialAdapter::SOURCES['credit']['url']], array_values(array_unique(array_column($observations, 'source_url'))));
    }

    private function publishBi(): DatasetVersion
    {
        return app(DatasetImporter::class)->publish($this->stageBi(), null);
    }

    private function stageBi(): DatasetVersion
    {
        $source = DataSource::where('code', 'BI')->firstOrFail();
        $source->update(['adapter' => BiSekiProvincialAdapter::PROFILE, 'download_url' => BiSekiProvincialAdapter::SOURCES['credit']['url'], 'method' => 'official_file', 'enabled' => true]);
        $dataset = Dataset::create(['code' => BiSekiProvincialAdapter::DATASET_CODE, 'name' => 'BI provincial comparison fixture', 'data_source_id' => $source->id, 'status' => 'draft']);
        $checksum = hash('sha256', BiSekiProvincialAdapter::SOURCES['credit']['sha256'].':'.BiSekiProvincialAdapter::SOURCES['deposits']['sha256']);
        $datasetVersion = BiSekiProvincialAdapter::EDITION_ID.'-'.substr($checksum, 0, 12);
        $files = [];
        foreach (BiSekiProvincialAdapter::SOURCES as $kind => $profile) {
            $path = 'snapshots/bi-seki/api-'.$kind.'.xls';
            Storage::disk('local')->put($path, file_get_contents(base_path('tests/Fixtures/bi-seki-'.$kind.'-2026-08.xls')));
            $files[] = ['table' => $profile['table'], 'url' => $profile['url'], 'resolved_url' => $profile['url'], 'sha256' => $profile['sha256'], 'path' => $path, 'last_modified' => $kind === 'credit' ? '2026-10-05T01:57:12+00:00' : '2026-10-05T02:03:59+00:00'];
        }
        $manifest = [
            'profile' => BiSekiProvincialAdapter::PROFILE, 'geography_id' => BiProvinceRegistry::GEOGRAPHY_ID,
            'release_date' => BiSekiProvincialAdapter::RELEASE_DATE, 'publication_url' => BiSekiProvincialAdapter::PUBLICATION_URL,
            'retrieved_at' => self::RETRIEVED_AT, 'checksum' => $checksum, 'dataset_version' => $datasetVersion, 'files' => $files,
        ];
        Storage::disk('local')->put(self::MANIFEST_PATH, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if (self::$parsedRows === null) {
            $parser = app(BiSekiProvincialAdapter::class);
            self::$parsedRows = array_merge(
                $parser->parse(base_path('tests/Fixtures/bi-seki-credit-2026-08.xls'), 'credit', self::RETRIEVED_AT, $datasetVersion),
                $parser->parse(base_path('tests/Fixtures/bi-seki-deposits-2026-08.xls'), 'deposits', self::RETRIEVED_AT, $datasetVersion),
            );
        }
        $version = app(DatasetImporter::class)->stage($dataset, self::$parsedRows, self::MANIFEST_PATH, null);
        $this->assertSame([], $version->issues);

        return $version;
    }
}
