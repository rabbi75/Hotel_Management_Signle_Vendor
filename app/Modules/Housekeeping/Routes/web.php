<?php

declare(strict_types=1);

use App\Modules\Housekeeping\Http\Controllers\HousekeepingTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:housekeeping'])->group(function (): void {
    Route::get('housekeeping', [HousekeepingTaskController::class, 'index'])->name('housekeeping.index');
    Route::get('housekeeping/create', [HousekeepingTaskController::class, 'create'])->name('housekeeping.create');
    Route::post('housekeeping', [HousekeepingTaskController::class, 'store'])->name('housekeeping.store');
    Route::get('housekeeping/{housekeepingTask}', [HousekeepingTaskController::class, 'show'])->name('housekeeping.show');
    Route::post('housekeeping/{housekeepingTask}/assign', [HousekeepingTaskController::class, 'assign'])->name('housekeeping.assign');
    Route::post('housekeeping/{housekeepingTask}/start', [HousekeepingTaskController::class, 'start'])->name('housekeeping.start');
    Route::post('housekeeping/{housekeepingTask}/complete', [HousekeepingTaskController::class, 'complete'])->name('housekeeping.complete');
    Route::post('housekeeping/{housekeepingTask}/cancel', [HousekeepingTaskController::class, 'cancel'])->name('housekeeping.cancel');
});
