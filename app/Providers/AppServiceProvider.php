<?php

namespace App\Providers;

use App\Models\User;
use App\Services\QueueHealth;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::getAccessTokenFromRequestUsing(fn (Request $request): ?string => null);
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasPermission($ability) ? true : null);
        Vite::useCspNonce();
        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        Queue::looping(function (): void {
            QueueHealth::heartbeat();
        });
    }
}
