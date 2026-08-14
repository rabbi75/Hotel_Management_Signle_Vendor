<?php

declare(strict_types=1);

use App\Modules\Media\Http\Controllers\MediaController;
use App\Modules\Media\Http\Controllers\MediaEditorController;
use App\Modules\Media\Http\Controllers\MediaFolderController;
use App\Modules\Media\Http\Controllers\MediaUploadController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Media library routes
|------------------------------------------------------------------------------
|
| Fixed segments (`upload`, `bulk`, `folders`) come before the `{asset}`
| wildcard so none of them is captured as an id.
|
*/

Route::middleware(['auth', 'verified'])->prefix('media')->name('media.')->group(function (): void {

    Route::get('/', [MediaController::class, 'index'])->name('index');

    Route::post('upload', MediaUploadController::class)->name('upload');
    Route::post('bulk', [MediaController::class, 'bulk'])->name('bulk');

    Route::prefix('folders')->name('folders.')->group(function (): void {
        Route::post('/', [MediaFolderController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{folder}', [MediaFolderController::class, 'update'])->name('update');
        Route::delete('{folder}', [MediaFolderController::class, 'destroy'])->name('destroy');
    });

    Route::get('{asset}', [MediaController::class, 'show'])->name('show');
    Route::match(['put', 'patch'], '{asset}', [MediaController::class, 'update'])->name('update');
    Route::delete('{asset}', [MediaController::class, 'destroy'])->name('destroy');
    Route::post('{asset}/edit', MediaEditorController::class)->name('edit');
});
