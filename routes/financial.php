<?php

use App\Http\Controllers\FinancialController as F;
use App\Http\Controllers\ProvinceComparisonController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('throttle:public-read')->group(function (): void {
    Route::get('analytics', [F::class, 'analytics']);
    Route::get('province-comparison/catalog', [ProvinceComparisonController::class, 'catalog']);
    Route::get('province-comparison', [ProvinceComparisonController::class, 'compare']);
    Route::get('indicators', [F::class, 'indicators']);
    Route::get('observations/export', [F::class, 'observations']);
    Route::get('observations', [F::class, 'observations']);
    Route::get('risk-signals', [F::class, 'signals']);
    Route::get('stress-scenarios', [F::class, 'scenarios']);
    Route::post('stress-tests', [F::class, 'stress'])->middleware('throttle:20,1,stress');
});
Route::prefix('api/v1/admin')->middleware(['auth:sanctum', 'active', 'can:admin.access', 'admin.mfa', 'throttle:60,1,financial-admin'])->group(function (): void {
    Route::get('sources', [F::class, 'sources'])->middleware('can:sources.view');
    Route::put('sources/{source}', [F::class, 'sourceSave'])->middleware('can:sources.manage');
    Route::middleware('can:data.manage')->group(function (): void {
        Route::get('datasets', [F::class, 'datasets']);
        Route::post('datasets', [F::class, 'datasetSave']);
        Route::get('datasets/{dataset}/versions', [F::class, 'versions']);
        Route::post('datasets/{dataset}/import', [F::class, 'import'])->middleware('throttle:10,1,imports');
        Route::get('dataset-versions/{version}', [F::class, 'version']);
        Route::post('dataset-versions/{version}/publish', [F::class, 'publish']);
        Route::post('dataset-versions/{version}/rollback', [F::class, 'publish']);
        Route::get('import-template', [F::class, 'template']);
        Route::get('indicators', [F::class, 'indicators']);
        Route::post('indicators', [F::class, 'indicatorSave']);
        Route::put('indicators/{indicator}', [F::class, 'indicatorSave']);
        Route::get('risk-rules', [F::class, 'rules']);
        Route::post('risk-rules', [F::class, 'ruleSave']);
        Route::put('risk-rules/{rule}', [F::class, 'ruleSave']);
        Route::get('scenarios', [F::class, 'scenarios']);
        Route::post('scenarios', [F::class, 'scenarioSave']);
        Route::put('scenarios/{scenario}', [F::class, 'scenarioSave']);
    });
    Route::middleware('can:sources.manage')->group(function (): void {
        Route::get('sync/runs', [F::class, 'syncRuns']);
        Route::post('sync/runs', [F::class, 'sync']);
        Route::get('sync/runs/{run}', [F::class, 'syncRun']);
        Route::post('sync/runs/{run}/retry', [F::class, 'retry']);
    });
});
