<?php

declare(strict_types=1);

use App\Modules\Search\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Search routes
|------------------------------------------------------------------------------
|
| Throttled on its own bucket: the command palette fires a request per keystroke
| burst, so it must not share a limit with ordinary page traffic.
|
*/

Route::get('search', SearchController::class)
    ->middleware(['auth', 'throttle:search'])
    ->name('search.index');
