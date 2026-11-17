<?php

namespace App\Services;

use Illuminate\Http\Request;

class AdminMfaService
{
    public static function verified(Request $request): bool
    {
        if (! config('finwatch.admin_mfa_required')) {
            return true;
        }

        $user = $request->user();
        $challenge = $request->session()->get('admin_mfa', []);

        return $user !== null
            && $user->getRawOriginal('admin_totp_secret') !== null
            && is_array($challenge)
            && ($challenge['user_id'] ?? null) === $user->id
            && ($challenge['auth_version'] ?? null) === $user->auth_version
            && ($challenge['expires_at'] ?? 0) > now()->timestamp;
    }
}
