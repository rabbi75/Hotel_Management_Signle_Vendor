<?php

declare(strict_types=1);

use App\Modules\CMS\Http\Controllers\MenuController;
use App\Modules\CMS\Http\Controllers\MenuItemController;
use App\Modules\CMS\Http\Controllers\PageBlockController;
use App\Modules\CMS\Http\Controllers\PageController;
use App\Modules\CMS\Http\Controllers\PagePreviewController;
use App\Modules\CMS\Http\Controllers\PagePublishController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| CMS administration routes
|------------------------------------------------------------------------------
|
| The public catch-all is deliberately *not* here: it is registered from the
| service provider's `booted` callback so that it lands after every other
| module's routes. See CMSServiceProvider.
|
*/

Route::middleware(['auth', 'verified', 'plan.feature:cms'])->prefix('cms')->name('cms.')->group(function (): void {

    Route::prefix('pages')->name('pages.')->group(function (): void {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::get('create', [PageController::class, 'create'])->name('create');
        Route::post('/', [PageController::class, 'store'])->name('store');

        Route::get('{page}/edit', [PageController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '{page}', [PageController::class, 'update'])->name('update');
        Route::post('{page}/duplicate', [PageController::class, 'duplicate'])->name('duplicate');
        Route::delete('{page}', [PageController::class, 'destroy'])->name('destroy');

        // Publishing
        Route::post('{page}/publish', [PagePublishController::class, 'store'])->name('publish');
        Route::delete('{page}/publish', [PagePublishController::class, 'destroy'])->name('unpublish');
        Route::post('{page}/schedule', [PagePublishController::class, 'schedule'])->name('schedule');

        // Preview: minting a link needs the editor's session; following it needs
        // a valid signature as well.
        Route::post('{page}/preview', [PagePreviewController::class, 'store'])->name('preview.create');
        Route::get('{page}/preview', [PagePreviewController::class, 'show'])
            ->middleware('signed')
            ->name('preview');

        // Blocks
        Route::post('{page}/blocks', [PageBlockController::class, 'store'])->name('blocks.store');
        Route::post('{page}/blocks/reorder', [PageBlockController::class, 'reorder'])->name('blocks.reorder');
    });

    Route::prefix('blocks')->name('blocks.')->group(function (): void {
        Route::match(['put', 'patch'], '{block}', [PageBlockController::class, 'update'])->name('update');
        Route::post('{block}/toggle', [PageBlockController::class, 'toggle'])->name('toggle');
        Route::delete('{block}', [PageBlockController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('menus')->name('menus.')->group(function (): void {
        Route::get('/', [MenuController::class, 'index'])->name('index');
        Route::post('/', [MenuController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{menu}', [MenuController::class, 'update'])->name('update');
        Route::delete('{menu}', [MenuController::class, 'destroy'])->name('destroy');

        Route::post('{menu}/items', [MenuItemController::class, 'store'])->name('items.store');
        Route::post('{menu}/items/reorder', [MenuItemController::class, 'reorder'])->name('items.reorder');
    });

    Route::prefix('menu-items')->name('menu-items.')->group(function (): void {
        Route::match(['put', 'patch'], '{item}', [MenuItemController::class, 'update'])->name('update');
        Route::delete('{item}', [MenuItemController::class, 'destroy'])->name('destroy');
    });
});
