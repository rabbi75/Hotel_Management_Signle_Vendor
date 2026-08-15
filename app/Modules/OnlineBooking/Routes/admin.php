<?php

declare(strict_types=1);

use App\Modules\OnlineBooking\Http\Controllers\Web\BookingPaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('booking-payments', [BookingPaymentMethodController::class, 'index'])->name('booking-payments.index');
    Route::put('booking-payments/{booking_payment_method}', [BookingPaymentMethodController::class, 'update'])
        ->name('booking-payments.update');
});
