<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminMfaService;
use App\Services\AuditService;
use App\Services\GoogleIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

class SessionController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => $user !== null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'roles' => $user->roles()->orderBy('name')->pluck('name'),
                'permissions' => $user->permissionNames(),
            ] : null,
            'google_configured' => GoogleIdentityService::configured(),
            'admin_mfa_required' => (bool) config('finwatch.admin_mfa_required'),
            'admin_mfa_verified' => $user !== null && AdminMfaService::verified($request),
            'admin_mfa_enrolled' => $user !== null && $user->getRawOriginal('admin_totp_secret') !== null,
            'admin_mfa_enrollment_allowed' => $user !== null && $user->hasPermission('admin.access') && $user->getRawOriginal('admin_totp_secret') === null && is_int($request->session()->get('oauth_authenticated_at')) && $request->session()->get('oauth_authenticated_at') > now()->subMinutes(10)->timestamp && $request->session()->get('oauth_authenticated_at') <= now()->timestamp,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function logout(Request $request): Response
    {
        AuditService::record('auth.logout', $request->user());
        Auth::guard('web')->logout();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function stepUp(Request $request, Google2FA $authenticator): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        $userId = $request->user()->id;
        $authVersion = $request->session()->get('auth_version');

        $result = DB::transaction(function () use ($userId, $authVersion, $validated, $authenticator): array {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();

            if ($user->auth_version !== $authVersion || ! $user->hasPermission('admin.access')) {
                return ['error' => 'session_revoked', 'status' => 401];
            }

            if (! $user->admin_totp_secret) {
                return ['error' => 'mfa_setup_required', 'status' => 423];
            }

            try {
                $step = $authenticator->verifyKeyNewer($user->admin_totp_secret, $validated['code'], (int) $user->admin_totp_last_step, 1);
            } catch (Throwable) {
                $step = false;
            }

            if ($step === false) {
                AuditService::record('auth.mfa_failed', $user, ['reason' => 'invalid_code']);

                return ['error' => 'invalid_mfa_code', 'status' => 422];
            }

            $user->forceFill(['admin_totp_last_step' => (int) $step])->save();
            AuditService::record('auth.mfa_verified', $user);

            return ['user' => $user];
        });

        if (isset($result['error'])) {
            return response()->json([
                'message' => match ($result['error']) {
                    'mfa_setup_required' => 'Authenticator belum didaftarkan. Hubungi pengelola server.',
                    'session_revoked' => 'Sesi berakhir. Silakan masuk kembali.',
                    default => 'Kode tidak valid atau sudah digunakan. Gunakan kode baru.',
                },
                'error' => $result['error'],
            ], $result['status']);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_mfa', [
            'user_id' => $result['user']->id,
            'auth_version' => $result['user']->auth_version,
            'expires_at' => now()->timestamp + (int) config('finwatch.admin_mfa_ttl_seconds'),
        ]);

        return response()->json(['message' => 'Verifikasi administrator berhasil.']);
    }
}
