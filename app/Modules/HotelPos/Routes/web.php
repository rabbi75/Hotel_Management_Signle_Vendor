<?php

declare(strict_types=1);

use App\Modules\HotelPos\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:hotel_pos'])->group(function (): void {
    Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
});
