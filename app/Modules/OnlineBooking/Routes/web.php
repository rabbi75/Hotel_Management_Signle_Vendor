<?php

declare(strict_types=1);

use App\Modules\OnlineBooking\Http\Controllers\Web\PublicBookingController;
use Illuminate\Support\Facades\Route;

Route::get('book', [PublicBookingController::class, 'index'])->name('booking.index');
Route::get('book/{slug}/confirmation/{number}', [PublicBookingController::class, 'confirmation'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->where('number', '[A-Za-z0-9\-]+')
    ->name('booking.confirmation');
Route::get('book/{slug}', [PublicBookingController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('booking.show');
Route::post('book/{slug}', [PublicBookingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('booking.store');
