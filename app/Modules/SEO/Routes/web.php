<?php

declare(strict_types=1);

use App\Modules\SEO\Http\Controllers\RobotsController;
use App\Modules\SEO\Http\Controllers\SeoMetaController;
use App\Modules\SEO\Http\Controllers\SeoSettingsController;
use App\Modules\SEO\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:seo'])->prefix(panel_prefix('seo'))->name('seo.')->group(function (): void {
    Route::get('/', [SeoSettingsController::class, 'index'])->name('index');

    Route::get('settings', [SeoSettingsController::class, 'edit'])->name('settings.edit');
    Route::match(['put', 'patch'], 'settings', [SeoSettingsController::class, 'update'])->name('settings.update');

    Route::post('sitemap', [SitemapController::class, 'store'])->name('sitemap.generate');

    // Consumed by the reusable SEO panel, which is embedded in other modules'
    // editors and fetches on demand rather than bloating every page payload.
    Route::prefix('meta')->name('meta.')->group(function (): void {
        Route::get('{type}/{id}', [SeoMetaController::class, 'show'])->whereNumber('id')->name('show');
        Route::match(['put', 'patch'], '/', [SeoMetaController::class, 'update'])->name('update');
        Route::delete('{type}/{id}', [SeoMetaController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });
});

/*
| Crawler-facing, unauthenticated. robots.txt is dynamic on purpose — see
| RobotsController — and the sitemap is served through the application so the
| same switch governs both.
*/
Route::get('robots.txt', RobotsController::class)->name('robots');
Route::get('sitemap.xml', [SitemapController::class, 'show'])->name('sitemap');
