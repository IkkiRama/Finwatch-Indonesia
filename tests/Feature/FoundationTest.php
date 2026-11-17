<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\DataSourceSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolesAndPermissionsSeeder::class, DataSourceSeeder::class]);
    }

    public function test_public_data_is_honestly_empty_and_source_seed_is_idempotent(): void
    {
        $this->seed(DataSourceSeeder::class);
        $this->getJson('/api/v1/overview')->assertOk()->assertJsonPath('published_datasets', 0)->assertJsonPath('observations', 0)->assertJsonPath('latest_period', null)->assertJsonPath('years', [])->assertJsonPath('sources_count', 5);
        $this->getJson('/api/v1/sources')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.0.status', 'awaiting_verification');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_years_come_only_from_published_observations_instead_of_calendar(): void
    {
        $source = DataSource::firstOrFail();
        foreach ([['published', '2024-01-01'], ['draft', '2027-01-01']] as [$status, $period]) {
            $datasetId = DB::table('datasets')->insertGetId(['data_source_id' => $source->id, 'code' => $status, 'name' => 'Isolated test fixture', 'status' => $status, 'published_at' => now()]);
            DB::table('indicator_observations')->insert(['dataset_id' => $datasetId, 'indicator_code' => 'test', 'period_date' => $period, 'value' => 1, 'unit' => 'test', 'scope' => 'Test', 'frequency' => 'annual', 'source_url' => 'https://example.test', 'release_date' => $period, 'retrieved_at' => now(), 'dataset_version' => 'fixture']);
        }
        $this->getJson('/api/v1/filters/years')->assertOk()->assertExactJson(['data' => [2024]]);
        $this->getJson('/api/v1/overview')->assertJsonPath('observations', 1)->assertJsonPath('latest_period', '2024-01-01');
    }

    public function test_guests_and_readers_cannot_access_any_admin_api(): void
    {
        $paths = ['overview', 'sources', 'audit-logs', 'health', 'users', 'roles'];
        foreach ($paths as $path) {
            $this->getJson('/api/v1/admin/'.$path)->assertUnauthorized();
        }
        $reader = User::factory()->create();
        $reader->roles()->attach(Role::where('name', 'reader')->firstOrFail());
        $this->actingAs($reader)->withSession(['auth_version' => $reader->auth_version]);
        foreach ($paths as $path) {
            $this->getJson('/api/v1/admin/'.$path)->assertForbidden();
        }
    }

    public function test_bearer_headers_are_ignored_for_cookie_only_authentication(): void
    {
        $this->withHeader('Authorization', 'Bearer 1|isolated-test-token')->getJson('/api/v1/admin/overview')->assertUnauthorized();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('authenticated', false);
    }

    public function test_admin_has_granular_permissions_and_real_counts(): void
    {
        config(['finwatch.admin_mfa_required' => false]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->actingAs($admin)->withSession(['auth_version' => $admin->auth_version]);
        $this->getJson('/api/v1/admin/overview')->assertOk()->assertJsonPath('metrics.sources', 5)->assertJsonPath('metrics.datasets', 0);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->getJson('/api/v1/admin/roles')->assertForbidden();
    }

    public function test_published_shell_security_headers_and_real_not_found_status(): void
    {
        $this->get('/')->assertOk()->assertSee('FINWATCH Indonesia')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
        $this->get('/masuk')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/artikel/missing')->assertNotFound();
        $this->getJson('/api/v1/nonexistent')->assertNotFound()->assertJsonPath('message', 'Endpoint tidak ditemukan.');
        $this->get('/admin')->assertRedirect('/masuk');
    }

    public function test_audit_search_matches_actor_without_exposing_private_identity(): void
    {
        config(['finwatch.admin_mfa_required' => false]);
        $admin = User::factory()->create(['name' => 'Auditor Fixture']);
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        AuditService::record('auth.login', $admin, ['channel' => 'google']);
        $this->actingAs($admin)->withSession(['auth_version' => $admin->auth_version]);
        $this->getJson('/api/v1/admin/audit-logs?query=Auditor')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.actor_name', 'Auditor Fixture')->assertDontSee($admin->email);
        $this->getJson('/api/v1/admin/audit-logs?sort=invalid')->assertUnprocessable();
    }

    public function test_csrf_is_enforced_with_testing_bypass_disabled(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/v1/logout')->assertStatus(419);
        $this->withSession(['_token' => 'test-csrf-token'])->withHeader('X-CSRF-TOKEN', 'test-csrf-token')->postJson('/api/v1/logout')->assertUnauthorized();
    }
}
