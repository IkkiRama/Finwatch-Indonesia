<?php

use App\Http\Controllers\Auth\AdminEnrollmentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:10,1,oauth-start')->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1,oauth-callback')->name('auth.google.callback');

Route::prefix('api/v1')->middleware(['active', 'throttle:120,1,identity-read'])->group(function (): void {
    Route::get('me', [SessionController::class, 'me']);
    Route::post('logout', [SessionController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('admin/step-up', [SessionController::class, 'stepUp'])
        ->middleware(['auth:sanctum', 'can:admin.access', 'throttle:5,1,admin-step-up']);
    Route::post('admin/mfa/enrollment', [AdminEnrollmentController::class, 'start'])
        ->middleware(['auth:sanctum', 'can:admin.access', 'throttle:5,1,admin-enrollment']);
    Route::post('admin/mfa/enrollment/confirm', [AdminEnrollmentController::class, 'confirm'])
        ->middleware(['auth:sanctum', 'can:admin.access', 'throttle:5,1,admin-enrollment']);
});
