<?php

declare(strict_types=1);

use App\Modules\Reservation\Http\Controllers\AvailabilityCalendarController;
use App\Modules\Reservation\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:reservations'])->group(function (): void {
    Route::get('reservations/calendar', AvailabilityCalendarController::class)->name('reservations.calendar');

    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->name('reservations.check-in');
    Route::post('reservations/{reservation}/check-out', [ReservationController::class, 'checkOut'])->name('reservations.check-out');

    Route::resource('reservations', ReservationController::class)->except(['destroy']);
});
