<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\ActivityLogController;
use App\Modules\Audit\Http\Controllers\LoginHistoryController;
use App\Modules\Audit\Http\Controllers\SecurityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('audit')
    ->name('audit.')
    ->group(function (): void {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
        Route::get('logins', [LoginHistoryController::class, 'index'])->name('logins.index');
        Route::get('security', [SecurityLogController::class, 'index'])->name('security.index');

        // Exports are the expensive, easily abused endpoints on these screens.
        Route::middleware('throttle:export')->group(function (): void {
            Route::get('activity/export', [ActivityLogController::class, 'export'])->name('activity.export');
            Route::get('logins/export', [LoginHistoryController::class, 'export'])->name('logins.export');
            Route::get('security/export', [SecurityLogController::class, 'export'])->name('security.export');
        });
    });
