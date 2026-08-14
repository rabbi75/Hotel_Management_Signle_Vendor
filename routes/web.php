<?php

declare(strict_types=1);

use App\Modules\CMS\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Web routes
|------------------------------------------------------------------------------
|
| Only application-wide routes belong here. Every feature registers its own
| routes from app/Modules/{Name}/Routes/web.php via its module service provider.
|
*/

Route::get('/', HomeController::class)->name('home');
