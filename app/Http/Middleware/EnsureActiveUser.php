<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->status !== 'active' || $request->session()->get('auth_version') !== $user->auth_version)) {
            Auth::guard('web')->logout();
            Auth::forgetGuards();
            Auth::shouldUse('web');
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Sesi berakhir. Silakan masuk kembali.', 'error' => 'session_revoked'], 401);
            }

            return redirect('/masuk?error=session_revoked');
        }

        return $next($request);
    }
}
