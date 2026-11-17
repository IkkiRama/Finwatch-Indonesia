<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\GoogleIdentityService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as ProviderRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): ProviderRedirectResponse
    {
        if (! GoogleIdentityService::configured()) {
            return redirect('/masuk?error=oauth_unavailable');
        }

        $returnTo = $request->query('return_to', '/');
        $request->session()->put('oauth.return_to', $this->returnTo($returnTo));

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request, GoogleIdentityService $identities): RedirectResponse
    {
        if (! GoogleIdentityService::configured()) {
            return redirect('/masuk?error=oauth_unavailable');
        }

        try {
            $identity = Socialite::driver('google')->user();
            $user = $identities->authenticate($identity);
        } catch (InvalidStateException) {
            return $this->failed('invalid_state');
        } catch (DomainException $exception) {
            return $this->failed($exception->getMessage());
        } catch (QueryException) {
            return $this->failed('identity_conflict');
        } catch (Throwable) {
            return $this->failed('provider_error');
        }

        $returnTo = $request->session()->pull('oauth.return_to', '/');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('admin_mfa');
        $request->session()->forget('admin_mfa_enrollment');
        $request->session()->put('auth_version', $user->auth_version);
        $request->session()->put('oauth_authenticated_at', now()->timestamp);

        return redirect($this->returnTo($returnTo));
    }

    private function returnTo(mixed $path): string
    {
        if (is_string($path) && (in_array($path, ['/', '/admin'], true) || preg_match('~^/artikel/[a-z0-9]+(?:-[a-z0-9]+)*(?:#komentar-[0-9]+)?$~D', $path))) {
            return $path;
        }

        return '/';
    }

    private function failed(string $reason): RedirectResponse
    {
        $allowed = ['invalid_state', 'unverified_email', 'invalid_identity', 'blocked_account', 'identity_conflict', 'provider_error'];
        $safeReason = in_array($reason, $allowed, true) ? $reason : 'provider_error';
        AuditService::record('auth.failed', null, ['reason' => $safeReason, 'channel' => 'google']);

        return redirect('/masuk?error='.$safeReason);
    }
}
