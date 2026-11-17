<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['finwatch.admin_mfa_required' => false]);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    private function act(User $user): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->actingAs($user, 'web')->withSession(['auth_version' => $user->auth_version]);
    }

    public function test_access_changes_revoke_target_sessions_and_prevent_self_or_owner_demotion(): void
    {
        $owner = $this->account('super_admin');
        $reader = $this->account('reader');
        DB::table('sessions')->insert(['id' => 'reader-old', 'user_id' => $reader->id, 'payload' => 'fixture', 'last_activity' => time()]);
        $this->act($owner);
        $this->putJson('/api/v1/admin/users/'.$reader->id, ['roles' => ['author', 'reader'], 'status' => 'active'])->assertOk();
        $this->assertSame($reader->auth_version + 1, $reader->fresh()->auth_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'reader-old']);
        $this->assertTrue($reader->fresh()->hasPermission('articles.create'));
        $this->putJson('/api/v1/admin/users/'.$owner->id, ['roles' => ['reader'], 'status' => 'blocked'])->assertForbidden();
        $otherOwner = $this->account('super_admin');
        $this->putJson('/api/v1/admin/users/'.$otherOwner->id, ['roles' => ['reader'], 'status' => 'blocked'])->assertForbidden();
        $this->putJson('/api/v1/admin/users/'.$reader->id, ['roles' => ['super_admin'], 'status' => 'active'])->assertUnprocessable();
    }

    public function test_operational_settings_are_allowlisted_audited_and_public_tagline_has_effect(): void
    {
        $this->act($this->account('super_admin'));
        $this->putJson('/api/v1/admin/settings', ['site_tagline' => 'Baca data dengan konteks.', 'comments_enabled' => false, 'sync_enabled' => false, 'GOOGLE_CLIENT_SECRET' => 'must-not-persist'])->assertOk();
        $this->getJson('/api/v1/site')->assertOk()->assertExactJson(['site_tagline' => 'Baca data dengan konteks.']);
        $this->assertDatabaseCount('system_settings', 3);
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.changed']);
        $this->postJson('/api/v1/admin/sync/runs')->assertStatus(423);
        $this->assertDatabaseCount('sync_runs', 0);
        $this->act($this->account('editor'));
        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->putJson('/api/v1/admin/settings', ['site_tagline' => 'x', 'comments_enabled' => true, 'sync_enabled' => true])->assertForbidden();
    }

    public function test_owner_management_and_private_system_lists_are_not_available_to_an_admin(): void
    {
        $admin = $this->account('admin');
        $reader = $this->account('reader');
        $this->act($admin);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->putJson('/api/v1/admin/users/'.$reader->id, ['roles' => ['author'], 'status' => 'active'])->assertForbidden();
        $this->getJson('/api/v1/admin/roles')->assertForbidden();
        $this->getJson('/api/v1/site')->assertDontSee('sync_enabled')->assertDontSee('GOOGLE_CLIENT_SECRET');
    }
}
