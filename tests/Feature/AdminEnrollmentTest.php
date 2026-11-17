<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['finwatch.admin_mfa_required' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function account(string $role = 'admin', bool $google = true): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        if ($google) {
            $user->socialIdentities()->create(['provider' => 'google', 'provider_subject' => 'fixture-'.$user->id, 'verified_email_at' => now()]);
        }

        return $user;
    }

    private function act(User $user, ?int $freshAt = null): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->actingAs($user, 'web')->withSession(['auth_version' => $user->auth_version, 'oauth_authenticated_at' => $freshAt ?? now()->timestamp]);
    }

    public function test_initial_enrollment_requires_a_fresh_verified_google_admin_session(): void
    {
        $this->postJson('/api/v1/admin/mfa/enrollment')->assertUnauthorized();
        $this->act($this->account('reader'));
        $this->postJson('/api/v1/admin/mfa/enrollment')->assertForbidden();
        $this->act($this->account('admin', false));
        $this->postJson('/api/v1/admin/mfa/enrollment')->assertForbidden();
        $admin = $this->account();
        $this->act($admin, now()->subMinutes(11)->timestamp);
        $this->postJson('/api/v1/admin/mfa/enrollment')->assertStatus(423);
        $this->assertNull($admin->fresh()->admin_totp_secret);
    }

    public function test_setup_key_is_session_only_until_confirmed_and_never_exposed_after_enrollment(): void
    {
        $admin = $this->account();
        $this->act($admin);
        $this->getJson('/api/v1/me')->assertJsonPath('admin_mfa_enrolled', false)->assertJsonPath('admin_mfa_enrollment_allowed', true);
        $setup = $this->postJson('/api/v1/admin/mfa/enrollment')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $this->assertSame(32, strlen($setup['secret']));
        $this->assertStringStartsWith('otpauth://totp/', $setup['otpauth_uri']);
        $this->assertNull($admin->fresh()->admin_totp_secret);
        $this->getJson('/api/v1/me')->assertDontSee($setup['secret']);
        $this->postJson('/api/v1/admin/mfa/enrollment')->assertJsonPath('secret', $setup['secret']);
        DB::table('sessions')->insert(['id' => 'old-other-session', 'user_id' => $admin->id, 'payload' => 'fixture', 'last_activity' => now()->timestamp]);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        $this->postJson('/api/v1/admin/mfa/enrollment/confirm', ['code' => $code])->assertOk()->assertSessionMissing('admin_mfa_enrollment');
        $fresh = $admin->fresh();
        $this->assertSame($setup['secret'], $fresh->admin_totp_secret);
        $this->assertNotSame($setup['secret'], $fresh->getRawOriginal('admin_totp_secret'));
        $this->assertSame($admin->auth_version + 1, $fresh->auth_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-other-session']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.mfa_enrolled']);
        $this->assertStringNotContainsString($setup['secret'], DB::table('audit_logs')->pluck('metadata')->implode(' '));
        Auth::forgetGuards();
        $this->actingAs($fresh, 'web');
        $this->getJson('/api/v1/me')->assertJsonPath('admin_mfa_enrolled', true)->assertJsonPath('admin_mfa_verified', true)->assertDontSee($setup['secret']);
        $this->getJson('/api/v1/admin/overview')->assertOk();
        $this->postJson('/api/v1/admin/mfa/enrollment')->assertStatus(409)->assertDontSee($setup['secret']);
        $this->postJson('/api/v1/admin/step-up', ['code' => $code])->assertUnprocessable();
    }

    public function test_wrong_code_does_not_install_secret_and_expired_pending_setup_is_rejected(): void
    {
        $admin = $this->account();
        $this->act($admin);
        $setup = $this->postJson('/api/v1/admin/mfa/enrollment')->assertOk()->json();
        $correct = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        $wrong = $correct === '000000' ? '999999' : '000000';
        $this->postJson('/api/v1/admin/mfa/enrollment/confirm', ['code' => $wrong])->assertUnprocessable();
        $this->assertNull($admin->fresh()->admin_totp_secret);
        $this->withSession(['admin_mfa_enrollment' => ['user_id' => $admin->id, 'auth_version' => $admin->auth_version, 'secret' => $setup['secret'], 'expires_at' => now()->subSecond()->timestamp]])
            ->postJson('/api/v1/admin/mfa/enrollment/confirm', ['code' => $correct])->assertStatus(423);
        $this->assertNull($admin->fresh()->admin_totp_secret);
    }

    public function test_preexisting_enrollment_and_revoked_sessions_cannot_replace_a_key(): void
    {
        $admin = $this->account();
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $admin->forceFill(['admin_totp_secret' => $secret])->save();
        $this->act($admin);
        $this->postJson('/api/v1/admin/mfa/enrollment')->assertStatus(409)->assertDontSee($secret);
        $this->assertSame($secret, $admin->fresh()->admin_totp_secret);
        $this->withSession(['auth_version' => $admin->auth_version - 1])->postJson('/api/v1/admin/mfa/enrollment')->assertUnauthorized();
        $this->assertSame($secret, $admin->fresh()->admin_totp_secret);
    }
}
