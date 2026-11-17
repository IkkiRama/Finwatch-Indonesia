<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

class AdminEnrollmentController extends Controller
{
    private function freshGoogleSession(Request $request): void
    {
        $timestamp = $request->session()->get('oauth_authenticated_at');
        abort_unless(is_int($timestamp) && $timestamp <= now()->timestamp && $timestamp > now()->subMinutes(10)->timestamp, 423, 'Masuk ulang dengan Google sebelum menyiapkan autentikator.');
        abort_unless($request->user()->hasVerifiedEmail() && $request->user()->socialIdentities()->where('provider', 'google')->whereNotNull('verified_email_at')->exists(), 403);
    }

    public function start(Request $request, Google2FA $authenticator): JsonResponse
    {
        $this->freshGoogleSession($request);
        $user = $request->user();
        abort_if($user->getRawOriginal('admin_totp_secret') !== null, 409, 'Autentikator sudah terdaftar. Gunakan kode yang ada; pemulihan hanya melalui pengelola server.');
        $pending = $request->session()->get('admin_mfa_enrollment');
        if (! is_array($pending) || ($pending['user_id'] ?? null) !== $user->id || ($pending['auth_version'] ?? null) !== $user->auth_version || ($pending['expires_at'] ?? 0) <= now()->timestamp) {
            $pending = ['user_id' => $user->id, 'auth_version' => $user->auth_version, 'expires_at' => now()->addMinutes(10)->timestamp, 'secret' => $authenticator->generateSecretKey(32)];
            $request->session()->put('admin_mfa_enrollment', $pending);
        }

        return response()->json(['secret' => $pending['secret'], 'otpauth_uri' => $authenticator->getQRCodeUrl('FINWATCH Indonesia', $user->email, $pending['secret']), 'expires_at' => $pending['expires_at']])->header('Cache-Control', 'private, no-store')->header('Pragma', 'no-cache');
    }

    public function confirm(Request $request, Google2FA $authenticator): JsonResponse
    {
        $this->freshGoogleSession($request);
        $input = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        $pending = $request->session()->get('admin_mfa_enrollment');
        $user = $request->user();
        abort_unless(is_array($pending) && ($pending['user_id'] ?? null) === $user->id && ($pending['auth_version'] ?? null) === $user->auth_version && ($pending['expires_at'] ?? 0) > now()->timestamp && is_string($pending['secret'] ?? null), 423, 'Persiapan autentikator kedaluwarsa. Mulai kembali dari langkah pertama.');

        $result = DB::transaction(function () use ($request, $user, $pending, $input, $authenticator): array {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active' && $locked->hasPermission('admin.access') && $locked->auth_version === $pending['auth_version'], 401, 'Sesi berubah. Masuk ulang dengan Google.');
            abort_if($locked->getRawOriginal('admin_totp_secret') !== null, 409, 'Autentikator sudah didaftarkan.');
            try {
                $step = $authenticator->verifyKeyNewer($pending['secret'], $input['code'], 0, 1);
            } catch (Throwable) {
                $step = false;
            }
            if ($step === false) {
                AuditService::record('auth.mfa_enrollment_failed', $locked, ['reason' => 'invalid_code']);

                return ['valid' => false];
            }
            $locked->forceFill(['admin_totp_secret' => $pending['secret'], 'admin_totp_last_step' => (int) $step, 'auth_version' => $locked->auth_version + 1])->save();
            DB::table('sessions')->where('user_id', $locked->id)->where('id', '!=', $request->session()->getId())->delete();
            AuditService::record('auth.mfa_enrolled', $locked, ['channel' => 'fresh_google_session', 'auth_version' => $locked->auth_version]);

            return ['valid' => true, 'auth_version' => $locked->auth_version];
        });
        if (! $result['valid']) {
            return response()->json(['message' => 'Kode belum cocok. Periksa waktu perangkat dan gunakan kode terbaru.'], 422)->header('Cache-Control', 'private, no-store');
        }
        $request->session()->forget('admin_mfa_enrollment');
        $request->session()->regenerate(true);
        $request->session()->put('auth_version', $result['auth_version']);
        $request->session()->put('admin_mfa', ['user_id' => $user->id, 'auth_version' => $result['auth_version'], 'expires_at' => now()->timestamp + (int) config('finwatch.admin_mfa_ttl_seconds')]);

        return response()->json(['message' => 'Autentikator aktif. Anda sudah masuk ke workspace.'])->header('Cache-Control', 'private, no-store');
    }
}
