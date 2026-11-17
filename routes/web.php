<?php

use App\Http\Controllers\AdminFoundationController;
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/financial.php';
require __DIR__.'/content.php';
require __DIR__.'/system.php';

Route::prefix('api/v1')->middleware('throttle:public-read')->group(function (): void {
    Route::get('/overview', [FoundationController::class, 'overview']);
    Route::get('/site', [FoundationController::class, 'site']);
    Route::get('/sources', [FoundationController::class, 'sources']);
    Route::get('/filters/years', [FoundationController::class, 'years']);
});
Route::prefix('api/v1/admin')->middleware(['auth:sanctum', 'active', 'can:admin.access', 'admin.mfa', 'throttle:60,1,admin-read'])->group(function (): void {
    Route::get('/overview', [AdminFoundationController::class, 'overview']);
    Route::get('/audit-logs', [AdminFoundationController::class, 'auditLogs'])->middleware('can:audit.view');
    Route::get('/health', [AdminFoundationController::class, 'health'])->middleware('can:system.view');
    Route::get('/users', [AdminFoundationController::class, 'users'])->middleware('can:users.manage');
    Route::get('/roles', [AdminFoundationController::class, 'roles'])->middleware('can:users.manage');
});

Route::get('/masuk', SpaController::class)->name('login');
foreach (['/', '/perbankan', '/pemantauan-risiko', '/peringatan-dini', '/simulasi-stres', '/riset', '/inklusi-dan-konsumen', '/artikel', '/jelajah-data', '/sumber-data', '/tentang'] as $path) {
    Route::get($path, SpaController::class);
}
Route::get('/admin/{path?}', SpaController::class)->where('path', '.*')->middleware(['auth:sanctum', 'active', 'can:admin.access']);
Route::fallback(function () {
    if (request()->is('api/*')) {
        return response()->json(['message' => 'Endpoint tidak ditemukan.'], 404);
    }

    return response()->view('app', ['title' => 'Halaman tidak ditemukan | FINWATCH Indonesia', 'noindex' => true], 404);
});
