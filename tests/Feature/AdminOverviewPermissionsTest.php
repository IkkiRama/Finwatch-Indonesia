<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOverviewPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['finwatch.admin_mfa_required' => false]);

        AuditLog::create([
            'event' => 'roles.super_admin_provisioned',
            'metadata' => ['target_user_id' => 9001, 'channel' => 'cli'],
            'created_at' => now(),
        ]);
    }

    public function test_admin_workspace_access_does_not_imply_access_to_audit_activity(): void
    {
        foreach (['author', 'editor', 'moderator'] as $roleName) {
            $user = User::factory()->create();
            $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
            $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);

            $this->getJson('/api/v1/admin/overview')
                ->assertOk()
                ->assertJsonPath('recent_activity', [])
                ->assertJsonMissing(['event' => 'roles.super_admin_provisioned']);
            $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        }
    }

    public function test_role_with_audit_permission_can_read_overview_activity(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);

        $this->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonCount(1, 'recent_activity')
            ->assertJsonPath('recent_activity.0.event', 'roles.super_admin_provisioned');
    }
}
