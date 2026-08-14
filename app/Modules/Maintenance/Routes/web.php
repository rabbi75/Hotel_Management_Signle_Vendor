<?php

declare(strict_types=1);

use App\Modules\Maintenance\Http\Controllers\MaintenanceRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:maintenance'])->group(function (): void {
    Route::get('maintenance', [MaintenanceRequestController::class, 'index'])->name('maintenance.index');
    Route::get('maintenance/create', [MaintenanceRequestController::class, 'create'])->name('maintenance.create');
    Route::post('maintenance', [MaintenanceRequestController::class, 'store'])->name('maintenance.store');
    Route::get('maintenance/{maintenanceRequest}', [MaintenanceRequestController::class, 'show'])->name('maintenance.show');
    Route::get('maintenance/{maintenanceRequest}/edit', [MaintenanceRequestController::class, 'edit'])->name('maintenance.edit');
    Route::put('maintenance/{maintenanceRequest}', [MaintenanceRequestController::class, 'update'])->name('maintenance.update');
    Route::post('maintenance/{maintenanceRequest}/assign', [MaintenanceRequestController::class, 'assign'])->name('maintenance.assign');
    Route::post('maintenance/{maintenanceRequest}/start', [MaintenanceRequestController::class, 'start'])->name('maintenance.start');
    Route::post('maintenance/{maintenanceRequest}/complete', [MaintenanceRequestController::class, 'complete'])->name('maintenance.complete');
    Route::post('maintenance/{maintenanceRequest}/cancel', [MaintenanceRequestController::class, 'cancel'])->name('maintenance.cancel');
});
