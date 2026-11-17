<?php

use App\Http\Controllers\SystemController as S;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/admin')->middleware(['auth:sanctum', 'active', 'can:admin.access', 'admin.mfa', 'throttle:30,1,system-admin'])->group(function (): void {
    Route::get('author-candidates', [S::class, 'candidates'])->middleware('can:categories.manage');
    Route::put('users/{user}', [S::class, 'updateUser'])->middleware('can:roles.manage');
    Route::get('settings', [S::class, 'settings'])->middleware('can:settings.manage');
    Route::put('settings', [S::class, 'saveSettings'])->middleware('can:settings.manage');
});
