<?php

declare(strict_types=1);

use App\Modules\Notification\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'preview'])->name('preview');
        Route::post('read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read_all');
        Route::delete('bulk', [NotificationController::class, 'destroy'])->name('destroy_bulk');
    });
