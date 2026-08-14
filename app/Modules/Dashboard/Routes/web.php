<?php

declare(strict_types=1);

use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Dashboard\Http\Controllers\WidgetLayoutController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Dashboard routes
|------------------------------------------------------------------------------
|
| The `dashboard` route name is load-bearing: routes/web.php redirects to it,
| config/fortify.php lands on it after login, and the frontend links to it.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {

    Route::get('dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::middleware('permission:dashboard.customize')
        ->prefix('dashboard/layout')
        ->name('dashboard.layout.')
        ->group(function (): void {
            Route::put('/', [WidgetLayoutController::class, 'update'])->name('update');
            Route::delete('/', [WidgetLayoutController::class, 'destroy'])->name('destroy');
        });
});
