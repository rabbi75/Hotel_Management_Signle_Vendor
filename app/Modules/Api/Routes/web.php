<?php

declare(strict_types=1);

use App\Modules\Api\Http\Controllers\ApiDocsController;
use App\Modules\Api\Http\Controllers\ApiLogController;
use App\Modules\Api\Http\Controllers\ApiTokenController;
use App\Modules\Api\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Developer area (Inertia)
|------------------------------------------------------------------------------
|
| The management UI for the public API. The API itself lives in
| Routes/public-api.php, which is registered separately with its own middleware.
|
*/

Route::middleware(['auth', 'verified'])->prefix('api-console')->name('api.')->group(function (): void {

    Route::prefix('tokens')->name('tokens.')->group(function (): void {
        Route::get('/', [ApiTokenController::class, 'index'])->name('index');
        Route::post('/', [ApiTokenController::class, 'store'])->name('store');
        Route::delete('{token}', [ApiTokenController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('webhooks')->name('webhooks.')->group(function (): void {
        Route::get('/', [WebhookController::class, 'index'])->name('index');
        Route::post('/', [WebhookController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{endpoint}', [WebhookController::class, 'update'])->name('update');
        Route::delete('{endpoint}', [WebhookController::class, 'destroy'])->name('destroy');
        Route::post('deliveries/{delivery}/redeliver', [WebhookController::class, 'redeliver'])->name('deliveries.redeliver');
    });

    Route::prefix('logs')->name('logs.')->group(function (): void {
        Route::get('/', [ApiLogController::class, 'index'])->name('index');
        Route::get('{log}', [ApiLogController::class, 'show'])->name('show');
    });

    Route::get('docs', ApiDocsController::class)->name('docs');
});
