<?php

declare(strict_types=1);

use App\Modules\Media\Http\Controllers\MediaPickerController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Media picker API
|------------------------------------------------------------------------------
|
| Consumed by the picker other modules (CMS, Blog) embed. Session-authenticated
| through the `web` guard so it inherits the same tenant resolution as the
| manager screen, and permission-checked by MediaAssetPolicy inside the
| controller.
|
*/

Route::middleware(['web', 'auth'])
    ->get('media/picker', MediaPickerController::class)
    ->name('media.picker');
