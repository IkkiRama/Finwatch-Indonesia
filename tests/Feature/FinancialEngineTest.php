<?php

namespace Tests\Feature;

use App\Jobs\SynchronizeSource;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\DataSource;
use App\Models\RiskRule;
use App\Models\SyncRun;
use App\Services\Analytics;
use App\Services\DatasetImporter;
use App\Services\OfficialDownload;
use App\Services\OjkSpiAdapter;
use Carbon\CarbonImmutable;
use Database\Seeders\DataSourceSeeder;
use Database\Seeders\OfficialDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class FinancialEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DataSourceSeeder::class);
        Storage::fake('local');
    }

    private function row(string $code, string $period, string $value, string $unit = '%', string $method = 'Same aggregate definition'): array
    {
        return [
            'indicator_code' => $code, 'label' => 'Test '.$code, 'period_date' => $period,
            'value' => $value, 'unit' => $unit, 'frequency' => 'monthly', 'scope' => 'Indonesia',
            'source_url' => 'https://www.ojk.go.id/publication.csv', 'release_date' => '2025-06-30',
            'retrieved_at' => '2025-07-01T00:00:00Z', 'dataset_version' => 'fixture-1',
            'methodology_note' => $method, 'sector' => 'banking', 'segment' => 'aggregate',
        ];
    }

    private function publish(array $rows, string $code = 'test-series'): DatasetVersion
    {
        $dataset = Dataset::create([
            'code' => $code, 'name' => 'Financial test fixture',
            'data_source_id' => DataSource::where('code', 'OJK')->firstOrFail()->id,
            'status' => 'draft',
        ]);
        $importer = app(DatasetImporter::class);
        $version = $importer->stage($dataset, $rows, 'snapshots/'.$code.'.csv', null);
        $this->assertSame([], $version->issues);

        return $importer->publish($version, null);
    }

    private function csv(array $rows): string
    {
        $file = fopen('php://temp', 'w+');
        fputcsv($file, DatasetImporter::COLUMNS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($file, array_map(fn (string $column): mixed => $row[$column] ?? '', DatasetImporter::COLUMNS), ',', '"', '');
        }
        rewind($file);
        $content = stream_get_contents($file);
        fclose($file);

        return $content;
    }

    public function test_curated_official_snapshot_preserves_units_scope_vintage_and_is_idempotent(): void
    {
        $this->seed(OfficialDataSeeder::class);
        $this->assertDatabaseCount('datasets', 4);
        $this->assertDatabaseCount('dataset_versions', 4);
        $this->assertDatabaseCount('indicator_observations', 108);

        $query = DB::table('indicator_observations');
        $credit = (clone $query)->where('indicator_code', 'credit')->where('period_date', '2025-06-30')->first();
        $this->assertEqualsWithDelta(8059.79275870, (float) $credit->value, 0.00000001);
        $this->assertSame('Rp triliun', $credit->unit);
        $this->assertSame('2025-08-29', $credit->release_date);
        $this->assertSame('SPI-Juni-2025', $credit->dataset_version);
        $this->assertSame('2026-10-25T21:44:55+00:00', Carbon::parse($credit->retrieved_at)->utc()->toIso8601String());
        $car = (clone $query)->where('indicator_code', 'car')->where('period_date', '2025-06-30')->first();
        $this->assertEqualsWithDelta(25.80745792, (float) $car->value, 0.00000001);
        $this->assertSame('%', $car->unit);

        $this->assertSame(13, (clone $query)->where('indicator_code', 'credit')->count());
        $this->assertDatabaseHas('indicator_observations', ['indicator_code' => 'inclusion', 'scope' => 'Indonesia', 'period_date' => '2025-01-01', 'value' => 80.51]);
        $this->assertDatabaseHas('indicator_observations', ['indicator_code' => 'literacy', 'scope' => 'Perdesaan', 'value' => 59.60]);
        $this->assertDatabaseHas('indicator_observations', ['indicator_code' => 'npl_gross', 'period_date' => '2025-06-30', 'value' => 2.22]);
        $this->assertDatabaseHas('indicator_observations', ['indicator_code' => 'appk_complaints', 'frequency' => 'snapshot', 'period_date' => '2025-08-15', 'value' => 31456]);
        $this->assertDatabaseHas('indicator_observations', ['indicator_code' => 'illegal_entities_stopped', 'period_date' => '2025-08-29', 'value' => 1840]);
        $this->assertDatabaseCount('users', 0);

        $checksums = DatasetVersion::orderBy('id')->pluck('checksum')->all();
        $this->seed(OfficialDataSeeder::class);
        $this->assertDatabaseCount('indicator_observations', 108);
        $this->assertDatabaseCount('dataset_versions', 4);
        $this->assertSame($checksums, DatasetVersion::orderBy('id')->pluck('checksum')->all());
        $this->getJson('/api/v1/overview')->assertOk()->assertJsonPath('observations', 108);
    }

    public function test_analytics_distinguishes_percentage_points_and_level_growth_and_method_breaks(): void
    {
        $this->publish([
            $this->row('ratio_test', '2025-01-31', '2'), $this->row('ratio_test', '2025-02-28', '3.5'),
            $this->row('level_test', '2025-01-31', '100', 'Rp triliun'), $this->row('level_test', '2025-02-28', '120', 'Rp triliun'),
            $this->row('break_test', '2025-01-31', '4', '%', 'Old coverage'), $this->row('break_test', '2025-02-28', '6', '%', 'Expanded coverage'),
        ]);
        $series = collect(app(Analytics::class)->series())->keyBy('code');
        $this->assertSame(1.5, $series['ratio_test']['change']);
        $this->assertSame('pp', $series['ratio_test']['change_unit']);
        $this->assertEqualsWithDelta(20, $series['level_test']['change'], 0.00001);
        $this->assertSame('%', $series['level_test']['change_unit']);
        $this->assertNull($series['break_test']['change']);
        $this->assertFalse($series['break_test']['comparable']);
        $this->assertCount(0, app(Analytics::class)->series('inclusion'));
        $this->assertCount(0, app(Analytics::class)->series(null, 2024));
    }

    public function test_statistics_exclude_the_current_observation_and_require_regular_comparable_history(): void
    {
        $rows = [];
        for ($index = 0; $index < 13; $index++) {
            $date = CarbonImmutable::parse('2024-01-01')->addMonths($index)->endOfMonth()->format('Y-m-d');
            $rows[] = $this->row('stat_test', $date, (string) ($index + 1));
        }
        $this->publish($rows);
        $series = app(Analytics::class)->series()[0];
        $this->assertSame(12, $series['statistics']['samples']);
        $this->assertEqualsWithDelta(6.5, $series['statistics']['mean'], 0.000001);
        $this->assertEqualsWithDelta(sqrt(143 / 12), $series['statistics']['standard_deviation'], 0.000001);
        $this->assertEqualsWithDelta(6.5 / sqrt(143 / 12), $series['statistics']['z_score'], 0.000001);
        $this->publish([$this->row('gap_test', '2025-01-31', '2'), $this->row('gap_test', '2025-03-31', '4')], 'gapped-series');
        $gap = collect(app(Analytics::class)->series())->firstWhere('code', 'gap_test');
        $this->assertFalse($gap['comparable']);
        $this->assertNull($gap['change']);
        $this->assertNull($gap['statistics']['z_score']);
    }

    public function test_ews_reports_the_evaluated_delta_and_does_not_pass_incomparable_observations(): void
    {
        $this->publish([
            $this->row('delta_test', '2025-01-31', '2'), $this->row('delta_test', '2025-02-28', '4'),
            $this->row('break_test', '2025-01-31', '2', '%', 'Old coverage'), $this->row('break_test', '2025-02-28', '4', '%', 'Expanded coverage'),
        ]);
        foreach (['delta_test', 'break_test'] as $code) {
            RiskRule::create(['name' => 'Change '.$code, 'indicator_code' => $code, 'operator' => 'change_gt', 'threshold' => 1, 'minimum_samples' => 2, 'severity' => 'watch', 'methodology' => 'Change above one pp; research fixture.', 'enabled' => true]);
        }
        $signals = collect(app(Analytics::class)->signals())->keyBy('indicator_code');
        $this->assertSame('triggered', $signals['delta_test']['status']);
        $this->assertSame(2.0, $signals['delta_test']['value']);
        $this->assertSame('pp', $signals['delta_test']['unit']);
        $this->assertSame('incomparable_data', $signals['break_test']['status']);
        $this->assertNull($signals['break_test']['value']);
    }

    public function test_stress_endpoint_uses_documented_equation_and_rejects_impossible_parameters(): void
    {
        $parameters = ['credit' => 1000, 'npl' => 2, 'car' => 20, 'npl_shock_pp' => 3, 'lgd_percent' => 40];
        $this->postJson('/api/v1/stress-tests', $parameters)->assertOk()
            ->assertJsonPath('data.additional_loss', 12)
            ->assertJsonPath('data.capital_before', 200)
            ->assertJsonPath('data.capital_after', 188)
            ->assertJsonPath('data.car_after', 18.8)
            ->assertJsonPath('data.npl_after', 5)
            ->assertJsonPath('data.model_version', 'sensitivity-1.0');
        $this->postJson('/api/v1/stress-tests', array_replace($parameters, ['credit' => 0]))->assertUnprocessable()->assertJsonValidationErrors('credit');
        $this->postJson('/api/v1/stress-tests', array_replace($parameters, ['npl' => 99]))->assertUnprocessable()->assertJsonValidationErrors('npl_shock_pp');
        $this->postJson('/api/v1/stress-tests', array_replace($parameters, ['lgd_percent' => -1]))->assertUnprocessable()->assertJsonValidationErrors('lgd_percent');
        $this->assertDatabaseCount('stress_scenarios', 0);
    }

    public function test_analytics_year_filter_evaluates_signals_from_the_same_year_and_sector(): void
    {
        $this->publish([
            $this->row('year_delta', '2024-11-30', '80'),
            $this->row('year_delta', '2024-12-31', '82'),
            $this->row('year_delta', '2025-05-31', '85'),
            $this->row('year_delta', '2025-06-30', '80'),
        ]);
        RiskRule::create(['name' => 'Perubahan tahunan fixture', 'indicator_code' => 'year_delta', 'operator' => 'change_gt', 'threshold' => 1, 'minimum_samples' => 2, 'severity' => 'watch', 'methodology' => 'Kenaikan antarobservasi di atas 1 pp.', 'enabled' => true]);

        $this->getJson('/api/v1/analytics?sector=banking&year=2024')->assertOk()
            ->assertJsonPath('data.0.period', '2024-12-31')
            ->assertJsonPath('signals.0.period', '2024-12-31')
            ->assertJsonPath('signals.0.value', 2)
            ->assertJsonPath('signals.0.samples', 2)
            ->assertJsonPath('signals.0.status', 'triggered');
        $this->getJson('/api/v1/analytics?sector=banking&year=2025')->assertOk()
            ->assertJsonPath('signals.0.period', '2025-06-30')
            ->assertJsonPath('signals.0.value', -5)
            ->assertJsonPath('signals.0.status', 'within_rule');
        $this->getJson('/api/v1/analytics?sector=inclusion&year=2024')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonCount(0, 'signals');
        $this->getJson('/api/v1/analytics?year=2023')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonCount(0, 'signals');
    }

    public function test_source_urls_reject_credentials_private_hosts_misleading_suffixes_and_unapproved_domains(): void
    {
        $download = app(OfficialDownload::class);
        $this->assertSame('www.ojk.go.id', $download->validateUrl('https://www.ojk.go.id/example.xlsx'));
        $rejected = [
            'http://www.ojk.go.id/example.csv', 'https://127.0.0.1/data.csv', 'https://www.ojk.go.id.evil.test/data.csv',
            'https://user:password@www.ojk.go.id/data.csv', 'https://www.ojk.go.id:8443/data.csv',
            'https://www.ojk.go.id/data.csv#fragment', 'https://www.ojk.go.id/data.csv?api_key=secret',
        ];
        foreach ($rejected as $url) {
            try {
                $download->validateUrl($url);
                $this->fail('Rejected URL accepted: '.$url);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('source_url', $exception->errors());
            }
        }
        $this->expectException(ValidationException::class);
        $download->validateUrl('https://www.bps.go.id/data.csv', ['www.ojk.go.id']);
    }

    public function test_csv_and_xlsx_formulas_are_preserved_as_text_and_rejected_without_calculation(): void
    {
        $row = $this->row('formula_test', '2025-01-31', '=1+1');
        Storage::disk('local')->put('formula.csv', $this->csv([$row]));
        $importer = app(DatasetImporter::class);
        $rows = $importer->parse(Storage::disk('local')->path('formula.csv'), 'csv');
        $this->assertSame('=1+1', $rows[0]['value']);
        $dataset = Dataset::create(['code' => 'formula-data', 'name' => 'Formula test', 'data_source_id' => DataSource::firstOrFail()->id, 'status' => 'draft']);
        $this->assertSame('invalid', $importer->stage($dataset, $rows, 'formula.csv', null)->status);

        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([DatasetImporter::COLUMNS, array_values($row)]);
        $writer = new Xlsx($book);
        $writer->setPreCalculateFormulas(false);
        $writer->save(Storage::disk('local')->path('formula.xlsx'));
        $book->disconnectWorksheets();
        $xlsxRows = $importer->parse(Storage::disk('local')->path('formula.xlsx'), 'xlsx');
        $this->assertSame('=1+1', $xlsxRows[0]['value']);
        $this->assertSame('invalid', $importer->stage($dataset, $xlsxRows, 'formula.xlsx', null)->status);
        $this->assertDatabaseCount('indicator_observations', 0);
    }

    public function test_sync_requires_review_preserves_previous_public_data_and_recognizes_unchanged_bytes(): void
    {
        $source = DataSource::where('code', 'OJK')->firstOrFail();
        $source->update(['enabled' => true, 'adapter' => 'normalized_csv', 'download_url' => 'https://www.ojk.go.id/data.csv', 'allowed_hosts' => ['www.ojk.go.id']]);
        $content = $this->csv([$this->row('sync_test', '2025-01-31', '4')]);
        $download = Mockery::mock(OfficialDownload::class);
        $download->shouldReceive('fetch')->twice()->with($source->download_url, ['www.ojk.go.id'])->andReturn($content);
        $importer = app(DatasetImporter::class);
        $run = SyncRun::create(['data_source_id' => $source->id, 'logs' => []]);
        (new SynchronizeSource($run->id))->handle($download, $importer);
        $run->refresh();
        $this->assertSame('needs_review', $run->status);
        $this->assertSame(100, $run->progress);
        $this->assertNotNull($run->finished_at);
        $this->assertDatabaseCount('indicator_observations', 0);
        $version = DatasetVersion::findOrFail($run->version_id);
        Storage::disk('local')->assertExists($version->raw_path);
        $importer->publish($version, null);
        $this->assertDatabaseCount('indicator_observations', 1);

        $next = SyncRun::create(['data_source_id' => $source->id, 'logs' => []]);
        (new SynchronizeSource($next->id))->handle($download, $importer);
        $this->assertSame('no_new_data', $next->fresh()->status);
        $this->assertDatabaseCount('dataset_versions', 1);
        $this->assertDatabaseCount('indicator_observations', 1);
    }

    public function test_spi_profile_rejects_an_altered_archive_before_loading_the_workbook(): void
    {
        $content = file_get_contents(database_path('sources/ojk-spi-june-2025.xlsx'));
        Storage::disk('local')->put('altered-spi.xlsx', $content."\n");
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Checksum arsip berbeda');
        app(OjkSpiAdapter::class)->parse(Storage::disk('local')->path('altered-spi.xlsx'), OfficialDataSeeder::SPI_URL);
    }

    public function test_manual_xlsx_import_still_rejects_external_links(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([DatasetImporter::COLUMNS, array_values($this->row('link_test', '2025-01-31', '2'))]);
        $path = Storage::disk('local')->path('external.xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $zip->addFromString('xl/externalLinks/externalLink1.xml', '<externalLink/>');
        $zip->close();

        $this->expectException(ValidationException::class);
        app(DatasetImporter::class)->parse($path, 'xlsx');
    }

    public function test_invalid_new_sync_version_cannot_replace_the_published_snapshot(): void
    {
        $source = DataSource::where('code', 'OJK')->firstOrFail();
        $source->update(['enabled' => true, 'adapter' => 'normalized_csv', 'download_url' => 'https://www.ojk.go.id/data.csv', 'allowed_hosts' => ['www.ojk.go.id']]);
        $good = $this->csv([$this->row('sync_test', '2025-01-31', '4')]);
        $bad = $this->csv([$this->row('sync_test', '2025-01-31', '101')]);
        $download = Mockery::mock(OfficialDownload::class);
        $download->shouldReceive('fetch')->twice()->andReturn($good, $bad);
        $importer = app(DatasetImporter::class);
        $first = SyncRun::create(['data_source_id' => $source->id, 'logs' => []]);
        (new SynchronizeSource($first->id))->handle($download, $importer);
        $version = DatasetVersion::findOrFail($first->fresh()->version_id);
        $importer->publish($version, null);

        $next = SyncRun::create(['data_source_id' => $source->id, 'logs' => []]);
        (new SynchronizeSource($next->id))->handle($download, $importer);
        $this->assertSame('failed', $next->fresh()->status);
        $this->assertSame(hash('sha256', $good), $source->fresh()->last_checksum);
        $this->assertSame('invalid', DatasetVersion::findOrFail($next->fresh()->version_id)->status);
        $this->assertSame($version->id, Dataset::findOrFail($version->dataset_id)->current_version_id);
        $this->getJson('/api/v1/observations')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.value', 4);
    }
}
