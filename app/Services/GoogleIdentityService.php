<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SocialIdentity;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as GoogleUser;

class GoogleIdentityService
{
    public static function configured(): bool
    {
        foreach (['client_id', 'client_secret', 'redirect'] as $key) {
            if (! is_string(config('services.google.'.$key)) || trim(config('services.google.'.$key)) === '') {
                return false;
            }
        }

        return true;
    }

    public function authenticate(GoogleUser $identity): User
    {
        $raw = $identity->getRaw();
        $subject = (string) $identity->getId();
        $email = mb_strtolower(trim((string) $identity->getEmail()));

        if (($raw['email_verified'] ?? $raw['verified_email'] ?? false) !== true) {
            throw new DomainException('unverified_email');
        }

        if (! preg_match('/^[A-Za-z0-9_-]{1,191}$/', $subject) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            throw new DomainException('invalid_identity');
        }

        return DB::transaction(function () use ($identity, $subject, $email): User {
            $socialIdentity = SocialIdentity::where('provider', 'google')
                ->where('provider_subject', $subject)->lockForUpdate()->first();
            $user = $socialIdentity ? User::whereKey($socialIdentity->user_id)->lockForUpdate()->firstOrFail() : null;

            if ($user && $user->status !== 'active') {
                throw new DomainException('blocked_account');
            }

            $emailOwner = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if ($emailOwner && (! $user || $emailOwner->id !== $user->id)) {
                throw new DomainException('identity_conflict');
            }

            $name = mb_substr(trim(strip_tags((string) $identity->getName())), 0, 120);
            $avatar = $this->safeAvatar($identity->getAvatar());

            if (! $user) {
                $user = User::create([
                    'name' => $name !== '' ? $name : 'Pembaca FINWATCH',
                    'email' => $email,
                    'password' => Str::password(64),
                ]);
                $user->roles()->attach(Role::where('name', 'reader')->firstOrFail());
                $socialIdentity = SocialIdentity::create([
                    'provider' => 'google',
                    'provider_subject' => $subject,
                    'user_id' => $user->id,
                    'verified_email_at' => now(),
                ]);
                AuditService::record('auth.registered', $user, ['channel' => 'google']);
            }

            $user->forceFill([
                'name' => $name !== '' ? $name : $user->name,
                'email' => $email,
                'email_verified_at' => now(),
                'avatar_url' => $avatar,
                'last_login_at' => now(),
            ])->save();
            $socialIdentity->forceFill(['verified_email_at' => now()])->save();
            AuditService::record('auth.login', $user, ['channel' => 'google']);

            return $user->fresh();
        });
    }

    private function safeAvatar(?string $url): ?string
    {
        if (! $url || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && ($host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com'))
            ? $url : null;
    }
}
