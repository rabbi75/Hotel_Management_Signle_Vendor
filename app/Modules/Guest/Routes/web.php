<?php

declare(strict_types=1);

use App\Modules\Guest\Http\Controllers\GuestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:reservations'])->group(function (): void {
    Route::resource('guests', GuestController::class);
});
