<?php

declare(strict_types=1);

use App\Modules\Hotel\Http\Controllers\BedController;
use App\Modules\Hotel\Http\Controllers\BuildingController;
use App\Modules\Hotel\Http\Controllers\FacilityController;
use App\Modules\Hotel\Http\Controllers\FloorController;
use App\Modules\Hotel\Http\Controllers\HotelController;
use App\Modules\Hotel\Http\Controllers\HotelSwitchController;
use App\Modules\Hotel\Http\Controllers\RoomController;
use App\Modules\Hotel\Http\Controllers\RoomTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:hotel_management'])->group(function (): void {
    Route::post('hotels/{hotel}/switch', HotelSwitchController::class)->name('hotels.switch');

    Route::resource('hotels', HotelController::class);
    Route::resource('buildings', BuildingController::class)->except(['show']);
    Route::resource('floors', FloorController::class)->except(['show']);
    Route::resource('room-types', RoomTypeController::class)
        ->parameters(['room-types' => 'room_type'])
        ->except(['show']);
    Route::resource('rooms', RoomController::class)->except(['show']);
    Route::resource('beds', BedController::class)->except(['show']);
    Route::resource('facilities', FacilityController::class)->except(['show']);
});
