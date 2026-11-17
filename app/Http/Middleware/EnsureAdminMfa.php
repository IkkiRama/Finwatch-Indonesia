<?php

namespace App\Http\Middleware;

use App\Services\AdminMfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AdminMfaService::verified($request)) {
            return response()->json([
                'message' => 'Verifikasi authenticator diperlukan untuk mengakses workspace.',
                'error' => 'admin_mfa_required',
            ], 423);
        }

        return $next($request);
    }
}
