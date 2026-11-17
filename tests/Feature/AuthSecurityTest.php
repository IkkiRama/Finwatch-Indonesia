<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
            'finwatch.admin_mfa_required' => true,
        ]);
    }

    public function test_guest_identity_is_safe_and_does_not_claim_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('user', null)
            ->assertJsonPath('google_configured', true);
        $this->getJson('/api/v1/admin/overview')->assertUnauthorized();
    }

    public function test_google_redirect_uses_state_and_only_identity_scopes(): void
    {
        $response = $this->get('/auth/google/redirect?return_to=https://evil.example');
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $scopes = explode(' ', $query['scope']);
        sort($scopes);
        $this->assertSame(['email', 'openid', 'profile'], $scopes);
        $this->assertNotEmpty($query['state']);
        $response->assertSessionHas('state', $query['state'])->assertSessionHas('oauth.return_to', '/');
    }

    public function test_real_socialite_driver_rejects_an_invalid_state_before_network_access(): void
    {
        $this->withSession(['state' => 'expected-state'])
            ->get('/auth/google/callback?state=wrong-state&code=invalid')
            ->assertRedirect('/masuk?error=invalid_state');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.failed']);
    }

    public function test_google_login_is_explicitly_unavailable_without_server_credentials(): void
    {
        config(['services.google.client_secret' => null]);
        $this->get('/auth/google/redirect')->assertRedirect('/masuk?error=oauth_unavailable');
        $this->getJson('/api/v1/me')->assertJsonPath('google_configured', false);
    }

    public function test_new_verified_subject_becomes_reader_even_when_client_requests_super_admin(): void
    {
        $this->mockGoogle($this->googleIdentity('google-new-subject'));
        $this->withSession(['admin_mfa' => ['user_id' => 999, 'expires_at' => PHP_INT_MAX]])
            ->get('/auth/google/callback?role=super_admin')->assertRedirect('/');

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['reader'], $user->roles()->pluck('name')->all());
        $this->assertFalse($user->hasPermission('admin.access'));
        $this->assertDatabaseHas('social_identities', ['provider' => 'google', 'provider_subject' => 'google-new-subject']);
        $this->getJson('/api/v1/me')->assertJsonPath('authenticated', true)
            ->assertJsonPath('admin_mfa_verified', false)
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.admin_totp_secret');
        $this->assertStringNotContainsString('provider-access-token', AuditLog::all()->toJson());
        $this->assertStringNotContainsString('provider-refresh-token', User::all()->toJson());
    }

    public function test_unverified_google_email_never_creates_an_account(): void
    {
        $this->mockGoogle($this->googleIdentity('unverified-subject', false));
        $this->get('/auth/google/callback')->assertRedirect('/masuk?error=unverified_email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_matching_email_with_a_different_subject_cannot_take_over_an_account(): void
    {
        $original = $this->userWithRole('super_admin', 'reader@example.test');
        $this->linkIdentity($original, 'original-subject');
        $this->mockGoogle($this->googleIdentity('new-subject', true, 'reader@example.test'));
        $this->get('/auth/google/callback')->assertRedirect('/masuk?error=identity_conflict');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_identities', 1);
        $this->assertSame($original->id, SocialIdentity::sole()->user_id);
    }

    public function test_existing_google_subject_keeps_its_roles_when_verified_email_changes(): void
    {
        $original = $this->userWithRole('editor', 'old@example.test');
        $this->linkIdentity($original, 'original-subject');
        $this->mockGoogle($this->googleIdentity('original-subject', true, 'new@example.test'));
        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('new@example.test', $original->fresh()->email);
        $this->assertTrue($original->fresh()->hasRole('editor'));
    }

    public function test_callback_never_uses_an_external_intended_redirect(): void
    {
        $this->mockGoogle($this->googleIdentity('safe-subject'));
        $this->withSession(['oauth.return_to' => '//evil.example/path', 'url.intended' => 'https://evil.example'])
            ->get('/auth/google/callback')->assertRedirect('/');
    }

    public function test_blocked_account_cannot_login_or_keep_using_a_session(): void
    {
        $user = $this->userWithRole('admin');
        $this->linkIdentity($user, 'blocked-subject');
        $user->forceFill(['status' => 'blocked'])->save();
        $this->mockGoogle($this->googleIdentity('blocked-subject', true, $user->email));
        $this->get('/auth/google/callback')->assertRedirect('/masuk?error=blocked_account');
        $this->assertGuest();
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version])
            ->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('error', 'session_revoked');
    }

    public function test_revoked_session_version_is_denied_on_the_next_request(): void
    {
        $user = $this->userWithRole('admin');
        $oldVersion = $user->auth_version;
        $user->forceFill(['auth_version' => $oldVersion + 1])->save();
        $this->actingAs($user)->withSession(['auth_version' => $oldVersion])
            ->getJson('/api/v1/admin/overview')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_revoked_api_session_returns_json_without_an_accept_header(): void
    {
        $user = $this->userWithRole('reader');
        $this->actingAs($user)->withSession(['auth_version' => 0])
            ->get('/api/v1/me')->assertUnauthorized()->assertJsonPath('error', 'session_revoked');
    }

    public function test_reader_cannot_enter_admin_and_editor_or_admin_cannot_manage_roles(): void
    {
        config(['finwatch.admin_mfa_required' => false]);
        $reader = $this->userWithRole('reader');
        $this->actingAs($reader)->withSession(['auth_version' => $reader->auth_version])
            ->getJson('/api/v1/admin/overview')->assertForbidden();

        foreach (['editor', 'admin'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->withSession(['auth_version' => $user->auth_version])
                ->getJson('/api/v1/admin/users')->assertForbidden();
            $this->assertFalse($user->hasPermission('roles.manage'));
        }
    }

    public function test_user_policy_denies_self_elevation_and_allows_authorized_management_of_others(): void
    {
        $owner = $this->userWithRole('super_admin');
        $reader = $this->userWithRole('reader');
        $editor = $this->userWithRole('editor');
        $this->assertFalse(Gate::forUser($owner)->allows('updateRole', $owner));
        $this->assertTrue(Gate::forUser($owner)->allows('updateRole', $reader));
        $this->assertFalse(Gate::forUser($editor)->allows('updateRole', $reader));
    }

    public function test_mfa_is_required_before_admin_data_and_enrollment_is_not_public(): void
    {
        $user = $this->userWithRole('admin');
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version])
            ->getJson('/api/v1/admin/overview')->assertStatus(423)->assertJsonPath('error', 'admin_mfa_required');
        $this->postJson('/api/v1/admin/step-up', ['code' => '123456'])
            ->assertStatus(423)->assertJsonPath('error', 'mfa_setup_required');
    }

    public function test_totp_is_encrypted_and_a_successful_code_cannot_be_replayed(): void
    {
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $user = $this->userWithRole('admin');
        $user->forceFill(['admin_totp_secret' => $secret, 'admin_totp_last_step' => 0])->save();
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('admin_totp_secret'));
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version])
            ->postJson('/api/v1/admin/step-up', ['code' => $code])->assertOk();
        $this->getJson('/api/v1/me')->assertJsonPath('admin_mfa_verified', true);
        $this->postJson('/api/v1/admin/step-up', ['code' => $code])
            ->assertUnprocessable()->assertJsonPath('error', 'invalid_mfa_code');
        $this->assertStringNotContainsString($secret, AuditLog::all()->toJson());
    }

    public function test_expired_or_different_user_mfa_challenge_cannot_unlock_admin(): void
    {
        $user = $this->userWithRole('admin');
        $this->actingAs($user)->withSession([
            'auth_version' => $user->auth_version,
            'admin_mfa' => ['user_id' => $user->id, 'auth_version' => $user->auth_version, 'expires_at' => now()->timestamp - 1],
        ])->getJson('/api/v1/admin/overview')->assertStatus(423);
        $this->withSession([
            'admin_mfa' => ['user_id' => $user->id + 1, 'auth_version' => $user->auth_version, 'expires_at' => now()->timestamp + 900],
        ])->getJson('/api/v1/admin/overview')->assertStatus(423);
    }

    public function test_mfa_attempts_are_rate_limited(): void
    {
        $user = $this->userWithRole('admin');
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/admin/step-up', ['code' => '123456'])->assertStatus(423);
        }

        $this->postJson('/api/v1/admin/step-up', ['code' => '123456'])->assertStatus(429);
    }

    public function test_logout_invalidates_identity_and_step_up_state(): void
    {
        $user = $this->userWithRole('reader');
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version, 'admin_mfa' => ['user_id' => $user->id]])
            ->postJson('/api/v1/logout')->assertNoContent()->assertSessionMissing('admin_mfa');
        $this->getJson('/api/v1/me')->assertJsonPath('authenticated', false);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'event' => 'auth.logout']);
    }

    public function test_super_admin_can_only_be_provisioned_for_an_existing_verified_subject(): void
    {
        $this->artisan('finwatch:provision-super-admin', ['--subject' => 'missing-subject'])->assertFailed();
        $user = $this->userWithRole('reader');
        $this->linkIdentity($user, 'verified-subject');
        $this->artisan('finwatch:provision-super-admin', ['--subject' => 'verified-subject'])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasRole('super_admin'));
        $this->assertSame(2, $user->fresh()->auth_version);
        $this->assertDatabaseHas('audit_logs', ['event' => 'roles.super_admin_provisioned']);
        $this->assertStringNotContainsString('verified-subject', AuditLog::all()->toJson());
    }

    public function test_cli_role_changes_are_audited_and_revoke_previous_sessions(): void
    {
        $user = $this->userWithRole('reader');
        $this->linkIdentity($user, 'role-subject');
        DB::table('sessions')->insert(['id' => 'test-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->artisan('finwatch:assign-role', ['subject' => 'role-subject', 'role' => 'editor'])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasRole('editor'));
        $this->assertDatabaseMissing('sessions', ['id' => 'test-session']);
        $this->assertSame(2, $user->fresh()->auth_version);
        $this->assertDatabaseHas('audit_logs', ['event' => 'roles.changed']);
        $this->artisan('finwatch:assign-role', ['subject' => 'role-subject', 'role' => 'super_admin'])->assertFailed();
    }

    public function test_mfa_enrollment_requires_an_admin_and_reset_is_deliberate(): void
    {
        $reader = $this->userWithRole('reader');
        $this->linkIdentity($reader, 'reader-subject');
        $this->artisan('finwatch:enroll-mfa', ['subject' => 'reader-subject'])->assertFailed();
        $this->assertNull($reader->fresh()->admin_totp_secret);

        $admin = $this->userWithRole('admin');
        $this->linkIdentity($admin, 'admin-subject');
        $this->artisan('finwatch:enroll-mfa', ['subject' => 'admin-subject'])->assertSuccessful();
        $firstSecret = $admin->fresh()->admin_totp_secret;
        $this->assertNotEmpty($firstSecret);
        $this->assertSame(2, $admin->fresh()->auth_version);
        $this->artisan('finwatch:enroll-mfa', ['subject' => 'admin-subject'])->assertFailed();
        $this->assertSame($firstSecret, $admin->fresh()->admin_totp_secret);
        $this->artisan('finwatch:enroll-mfa', ['subject' => 'admin-subject', '--reset' => true])->assertSuccessful();
        $this->assertNotSame($firstSecret, $admin->fresh()->admin_totp_secret);
        $this->assertSame(3, $admin->fresh()->auth_version);
        $this->assertStringNotContainsString($firstSecret, AuditLog::all()->toJson());
    }

    public function test_role_seeding_is_idempotent_and_never_creates_an_admin_account(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('role_user', 0);
    }

    public function test_sensitive_account_fields_are_not_mass_assignable_or_serialized(): void
    {
        $user = $this->userWithRole('reader');
        $user->fill(['auth_version' => 999, 'status' => 'blocked', 'admin_totp_secret' => 'bad-secret']);
        $this->assertSame(1, $user->auth_version);
        $this->assertSame('active', $user->status);
        $this->assertNull($user->admin_totp_secret);
        $this->assertArrayNotHasKey('email', $user->toArray());
        AuditService::record('test.redaction', $user, ['token' => 'bad-token', 'email' => $user->email, 'reason' => 'invalid_state']);
        $this->assertSame(['reason' => 'invalid_state'], AuditLog::where('event', 'test.redaction')->sole()->metadata);
    }

    private function userWithRole(string $role, ?string $email = null): User
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $user->roles()->attach(Role::where('name', $role)->sole());

        return $user;
    }

    private function linkIdentity(User $user, string $subject): SocialIdentity
    {
        return SocialIdentity::create([
            'provider' => 'google', 'provider_subject' => $subject, 'user_id' => $user->id, 'verified_email_at' => now(),
        ]);
    }

    private function googleIdentity(string $subject, bool $verified = true, string $email = 'reader@example.test'): GoogleUser
    {
        return (new GoogleUser)->setRaw(['sub' => $subject, 'email_verified' => $verified])
            ->map(['id' => $subject, 'name' => 'Pembaca Uji', 'email' => $email, 'avatar' => 'https://lh3.googleusercontent.com/test-avatar'])
            ->setToken('provider-access-token')->setRefreshToken('provider-refresh-token');
    }

    private function mockGoogle(GoogleUser $identity): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
