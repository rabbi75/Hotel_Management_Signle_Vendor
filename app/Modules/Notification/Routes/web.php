<?php

declare(strict_types=1);

use App\Modules\Notification\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])
            ->middleware('permission:notifications.view')
            ->name('index');

        Route::post('read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read_all');
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read_one');

        Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy_all');
        Route::delete('bulk', [NotificationController::class, 'destroy'])->name('destroy_bulk');
        Route::delete('{notification}', [NotificationController::class, 'destroy'])->name('destroy');

        Route::put('preferences', [NotificationController::class, 'preferences'])->name('preferences');
    });
