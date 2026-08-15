<?php

declare(strict_types=1);

use App\Modules\OnlineBooking\Http\Controllers\Web\CustomerAccountController;
use App\Modules\OnlineBooking\Http\Controllers\Web\CustomerAuthController;
use App\Modules\OnlineBooking\Http\Controllers\Web\PublicBookingController;
use Illuminate\Support\Facades\Route;

Route::get('book', [PublicBookingController::class, 'index'])->name('booking.index');
Route::get('book/{slug}/confirmation/{number}', [PublicBookingController::class, 'confirmation'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->where('number', '[A-Za-z0-9\-]+')
    ->name('booking.confirmation');
Route::get('book/{slug}/checkout', [PublicBookingController::class, 'checkout'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('booking.checkout');
Route::post('book/{slug}/checkout', [PublicBookingController::class, 'place'])
    ->middleware('throttle:10,1')
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('booking.place');
Route::get('book/{slug}/pay', [PublicBookingController::class, 'pay'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('booking.pay');
Route::post('book/{slug}/pay', [PublicBookingController::class, 'confirmPayment'])
    ->middleware('throttle:10,1')
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('booking.pay.store');
Route::get('book/{slug}', [PublicBookingController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('booking.show');
Route::post('book/{slug}', [PublicBookingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('booking.store');

Route::middleware('guest:customer')->group(function (): void {
    Route::get('account/login', [CustomerAuthController::class, 'createLogin'])->name('account.login');
    Route::post('account/login', [CustomerAuthController::class, 'login'])->name('account.login.store');
    Route::get('account/register', [CustomerAuthController::class, 'createRegister'])->name('account.register');
    Route::post('account/register', [CustomerAuthController::class, 'register'])->name('account.register.store');
});

Route::middleware('auth:customer')->group(function (): void {
    Route::post('account/logout', [CustomerAuthController::class, 'logout'])->name('account.logout');
    Route::get('account', [CustomerAccountController::class, 'dashboard'])->name('account.dashboard');
    Route::get('account/bookings', [CustomerAccountController::class, 'bookings'])->name('account.bookings');
    Route::get('account/bookings/{number}', [CustomerAccountController::class, 'show'])
        ->where('number', '[A-Za-z0-9\-]+')
        ->name('account.bookings.show');
    Route::get('account/profile', [CustomerAccountController::class, 'profile'])->name('account.profile');
    Route::put('account/profile', [CustomerAccountController::class, 'updateProfile'])->name('account.profile.update');
});
