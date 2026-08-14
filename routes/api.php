<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| API routes
|------------------------------------------------------------------------------
|
| Token-authenticated endpoints. Feature endpoints live in each module's
| Routes/api.php; only the identity endpoint is global.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user())->name('api.user');
});
