<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\DataSource;
use App\Models\Role;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\BiProvincialSync;
use App\Services\BiSekiProvincialAdapter;
use App\Services\DatasetImporter;
use App\Services\OfficialDownload;
use Carbon\CarbonImmutable;
use Database\Seeders\DataSourceSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BiProvincialImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-04T03:00:00Z'));
        $this->seed([DataSourceSeeder::class, RolesAndPermissionsSeeder::class]);
        Storage::fake('local');
    }

    private function fixture(string $kind): string
    {
        return base_path('tests/Fixtures/bi-seki-'.$kind.'-2026-08.xls');
    }

    private function downloader(array $changed = []): OfficialDownload
    {
        $download = Mockery::mock(OfficialDownload::class)->makePartial();
        foreach (BiSekiProvincialAdapter::SOURCES as $kind => $source) {
            $download->shouldReceive('fetchWithMetadata')->with($source['url'], ['bi.go.id', 'www.bi.go.id'])->andReturn([
                'content' => $changed[$kind] ?? file_get_contents($this->fixture($kind)),
                'url' => $source['url'], 'last_modified' => '2026-10-05T02:03:59+00:00',
                'retrieved_at' => '2026-11-04T02:00:00+00:00',
            ]);
        }

        return $download;
    }

    private function source(bool $enabled = true): DataSource
    {
        $source = DataSource::where('code', 'BI')->firstOrFail();
        $source->update(['adapter' => BiSekiProvincialAdapter::PROFILE, 'download_url' => BiSekiProvincialAdapter::SOURCES['credit']['url'], 'allowed_hosts' => ['bi.go.id', 'www.bi.go.id'], 'enabled' => $enabled]);

        return $source;
    }

    private function synchronize(DataSource $source, OfficialDownload $download): SyncRun
    {
        $run = SyncRun::create(['data_source_id' => $source->id, 'status' => 'checking', 'logs' => []]);
        app(BiProvincialSync::class)->synchronize($run, $source, $download, app(DatasetImporter::class), function (string $status, int $progress, string $message) use ($run): void {
            $logs = $run->fresh()->logs ?? [];
            $logs[] = compact('status', 'progress', 'message');
            $run->update(['status' => $status, 'progress' => $progress, 'logs' => $logs]);
        });

        return $run->fresh();
    }

    private function administrator(bool $google = true, string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        if ($google) {
            $user->socialIdentities()->create(['provider' => 'google', 'provider_subject' => 'bi-import-test-'.$user->id, 'verified_email_at' => now()]);
        }

        return $user;
    }

    public function test_real_workbooks_preserve_monthly_precision_and_exclude_annual_totals_and_memos(): void
    {
        $parser = app(BiSekiProvincialAdapter::class);
        $rows = [];
        foreach (BiSekiProvincialAdapter::SOURCES as $kind => $source) {
            $this->assertSame($source['sha256'], hash_file('sha256', $this->fixture($kind)));
            $parsed = $parser->parse($this->fixture($kind), $kind, '2026-11-04T02:00:00+00:00', 'verified-test-edition');
            $this->assertCount(680, $parsed);
            $this->assertCount(20, array_unique(array_column($parsed, 'period_date')));
            $this->assertCount(34, array_unique(array_column($parsed, 'scope')));
            $this->assertSame('2025-01-31', min(array_column($parsed, 'period_date')));
            $this->assertSame('2026-08-31', max(array_column($parsed, 'period_date')));
            $this->assertCount(34, array_filter($parsed, fn (array $row): bool => $row['period_date'] === '2025-12-31'));
            $this->assertSame(array_map(fn (int $id): string => 'row-'.$id, range(1, 34)), array_values(array_unique(array_column($parsed, 'scope'))));
            $this->assertSame(['Rp miliar'], array_values(array_unique(array_column($parsed, 'unit'))));
            $this->assertSame(['2026-10-15'], array_values(array_unique(array_column($parsed, 'release_date'))));
            $this->assertSame([$source['url']], array_values(array_unique(array_column($parsed, 'source_url'))));
            $rows[$kind] = collect($parsed)->where('period_date', '2026-08-31')->keyBy('scope');
        }
        $this->assertEqualsWithDelta(1126541.1526, (float) $rows['credit']['row-12']['value'], 0.0000001);
        $this->assertEqualsWithDelta(535521.7562, (float) $rows['credit']['row-13']['value'], 0.0000001);
        $this->assertEqualsWithDelta(763752.4404, (float) $rows['deposits']['row-12']['value'], 0.0000001);
        $this->assertEqualsWithDelta(489945.0455, (float) $rows['deposits']['row-13']['value'], 0.0000001);
    }

    public function test_changed_workbook_is_rejected_before_it_can_inherit_the_verified_publication_date(): void
    {
        Storage::disk('local')->put('changed-bi.xls', file_get_contents($this->fixture('credit')).'changed');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('berbeda dari edisi September 2026');
        app(BiSekiProvincialAdapter::class)->parse(Storage::disk('local')->path('changed-bi.xls'), 'credit');
    }

    public function test_sync_stages_two_tables_with_private_provenance_and_requires_review(): void
    {
        $source = $this->source();
        $run = $this->synchronize($source, $this->downloader());
        $this->assertSame('needs_review', $run->status);
        $this->assertSame(100, $run->progress);
        $this->assertNotNull($run->finished_at);
        $version = DatasetVersion::findOrFail($run->version_id);
        $this->assertSame('needs_review', $version->status);
        $this->assertSame([], $version->issues);
        $this->assertCount(1360, $version->rows);
        $this->assertDatabaseCount('indicator_observations', 0);
        $this->assertNull(Dataset::where('code', BiSekiProvincialAdapter::DATASET_CODE)->value('current_version_id'));
        $manifest = json_decode(Storage::disk('local')->get($version->raw_path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(BiSekiProvincialAdapter::PROFILE, $manifest['profile']);
        $this->assertSame(BiSekiProvincialAdapter::PUBLICATION_URL, $manifest['publication_url']);
        $this->assertSame('2026-10-15', $manifest['release_date']);
        $this->assertSame('2026-11-04T02:00:00+00:00', $manifest['retrieved_at']);
        $this->assertCount(2, $manifest['files']);
        foreach ($manifest['files'] as $file) {
            $this->assertSame('2026-10-05T02:03:59+00:00', $file['last_modified']);
            $this->assertSame($file['sha256'], hash('sha256', Storage::disk('local')->get($file['path'])));
        }
        $again = $this->synchronize($source->fresh(), $this->downloader());
        $this->assertSame('no_new_data', $again->status);
        $this->assertSame($version->id, $again->version_id);
        $this->assertDatabaseCount('dataset_versions', 1);
    }

    public function test_new_edition_is_kept_private_and_does_not_replace_published_data(): void
    {
        $source = $this->source();
        $run = $this->synchronize($source, $this->downloader());
        $published = app(DatasetImporter::class)->publish(DatasetVersion::findOrFail($run->version_id), null);
        $previousChecksum = $source->fresh()->last_checksum;
        $next = $this->synchronize($source->fresh(), $this->downloader(['credit' => file_get_contents($this->fixture('credit')).'new-edition']));
        $this->assertSame('manual_required', $next->status);
        $this->assertSame(100, $next->progress);
        $this->assertNull($next->version_id);
        $this->assertNotNull($next->finished_at);
        $this->assertSame($previousChecksum, $source->fresh()->last_checksum);
        $this->assertSame($published->id, Dataset::where('code', BiSekiProvincialAdapter::DATASET_CODE)->value('current_version_id'));
        $this->assertDatabaseCount('dataset_versions', 1);
        $this->assertDatabaseCount('indicator_observations', 1360);
        $this->assertDatabaseHas('audit_logs', ['event' => 'sync.profile_review_required']);
    }

    public function test_disabled_source_finishes_without_downloading_or_retrying(): void
    {
        $download = Mockery::mock(OfficialDownload::class);
        $download->shouldNotReceive('fetchWithMetadata');
        $run = $this->synchronize($this->source(false), $download);
        $this->assertSame('manual_required', $run->status);
        $this->assertSame(100, $run->progress);
        $this->assertNotNull($run->finished_at);
        $this->assertDatabaseCount('dataset_versions', 0);
    }

    public function test_cli_publishes_only_for_verified_google_operator_and_is_idempotent(): void
    {
        $admin = $this->administrator();
        $this->instance(OfficialDownload::class, $this->downloader());
        $this->assertSame(0, Artisan::call('finwatch:configure-bi-provinces', ['email' => $admin->email, '--publish' => true]), Artisan::output());
        $this->assertDatabaseCount('dataset_versions', 1);
        $this->assertDatabaseCount('indicator_observations', 1360);
        $this->assertDatabaseHas('data_sources', ['code' => 'BI', 'status' => 'active']);
        $this->assertDatabaseHas('dataset_versions', ['status' => 'published', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('sync_runs', ['status' => 'success', 'progress' => 100]);
        $this->assertSame(0, Artisan::call('finwatch:configure-bi-provinces', ['email' => $admin->email, '--publish' => true]), Artisan::output());
        $this->assertDatabaseCount('dataset_versions', 1);
        $this->assertDatabaseCount('indicator_observations', 1360);
    }

    public function test_cli_rejects_missing_google_identity_permissions_and_suspended_accounts(): void
    {
        $download = Mockery::mock(OfficialDownload::class);
        $download->shouldNotReceive('fetchWithMetadata');
        $this->instance(OfficialDownload::class, $download);
        $this->artisan('finwatch:configure-bi-provinces', ['email' => 'missing@example.test', '--publish' => true])->assertFailed();
        $localOnly = $this->administrator(false);
        $this->artisan('finwatch:configure-bi-provinces', ['email' => $localOnly->email, '--publish' => true])->assertFailed();
        $reader = $this->administrator(true, 'reader');
        $this->artisan('finwatch:configure-bi-provinces', ['email' => $reader->email, '--publish' => true])->assertFailed();
        $suspended = $this->administrator();
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->artisan('finwatch:configure-bi-provinces', ['email' => $suspended->email, '--publish' => true])->assertFailed();
        $this->assertDatabaseCount('dataset_versions', 0);
    }

    public function test_cli_respects_a_paused_scheduler_and_an_existing_sync(): void
    {
        $admin = $this->administrator();
        $download = Mockery::mock(OfficialDownload::class);
        $download->shouldNotReceive('fetchWithMetadata');
        $this->instance(OfficialDownload::class, $download);
        DB::table('system_settings')->insert(['key' => 'sync_enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('finwatch:configure-bi-provinces', ['email' => $admin->email])->assertFailed();
        DB::table('system_settings')->where('key', 'sync_enabled')->update(['value' => 'true']);
        SyncRun::create(['data_source_id' => $this->source()->id, 'status' => 'queued', 'logs' => []]);
        $this->artisan('finwatch:configure-bi-provinces', ['email' => $admin->email])->assertFailed();
        $this->assertDatabaseCount('dataset_versions', 0);
        $this->assertDatabaseCount('sync_runs', 1);
    }
}
