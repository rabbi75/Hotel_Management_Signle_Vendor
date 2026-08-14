<?php

declare(strict_types=1);

use App\Modules\OnlineBooking\Http\Controllers\V1\AvailabilityController;
use App\Modules\OnlineBooking\Http\Controllers\V1\BookingController;
use App\Modules\OnlineBooking\Http\Controllers\V1\HotelCatalogController;
use App\Modules\OnlineBooking\Http\Middleware\EnsureOnlineBookingApi;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureOnlineBookingApi::class])->group(function (): void {
    Route::prefix('hotels')->name('hotels.')->group(function (): void {
        Route::get('/', [HotelCatalogController::class, 'index'])->name('index');
        Route::get('{uuid}', [HotelCatalogController::class, 'show'])->name('show');
        Route::get('{uuid}/room-types', [HotelCatalogController::class, 'roomTypes'])->name('room-types');
        Route::get('{uuid}/availability', [AvailabilityController::class, 'show'])->name('availability');
        Route::post('{uuid}/bookings', [BookingController::class, 'store'])->name('bookings.store');
    });

    Route::get('bookings/{number}', [BookingController::class, 'show'])->name('bookings.show');
});
