<?php

declare(strict_types=1);

use App\Install\Http\Controllers\InstallController;
use App\Install\Http\Middleware\EnsureInstallerStep;
use App\Install\Http\Middleware\PrepareInstallerRuntime;
use App\Install\Http\Middleware\RedirectIfInstalled;
use Illuminate\Support\Facades\Route;

Route::middleware([
    PrepareInstallerRuntime::class,
    'web',
    RedirectIfInstalled::class,
])
    ->prefix('install')
    ->name('install.')
    ->group(function (): void {
        Route::get('/', [InstallController::class, 'welcome'])->name('welcome');

        Route::get('requirements', [InstallController::class, 'requirements'])
            ->middleware(EnsureInstallerStep::class.':requirements')
            ->name('requirements');

        Route::get('permissions', [InstallController::class, 'permissions'])
            ->middleware(EnsureInstallerStep::class.':permissions')
            ->name('permissions');

        Route::get('license', [InstallController::class, 'licenseForm'])
            ->middleware(EnsureInstallerStep::class.':license')
            ->name('license');
        Route::post('license', [InstallController::class, 'licenseStore'])
            ->middleware(EnsureInstallerStep::class.':license')
            ->name('license.store');

        Route::get('database', [InstallController::class, 'databaseForm'])
            ->middleware(EnsureInstallerStep::class.':database')
            ->name('database');
        Route::post('database', [InstallController::class, 'databaseStore'])
            ->middleware(EnsureInstallerStep::class.':database')
            ->name('database.store');

        Route::get('environment', [InstallController::class, 'environmentForm'])
            ->middleware(EnsureInstallerStep::class.':environment')
            ->name('environment');
        Route::post('environment', [InstallController::class, 'environmentStore'])
            ->middleware(EnsureInstallerStep::class.':environment')
            ->name('environment.store');

        Route::get('migrate', [InstallController::class, 'migrateForm'])
            ->middleware(EnsureInstallerStep::class.':migrate')
            ->name('migrate');
        Route::post('migrate', [InstallController::class, 'migrateRun'])
            ->middleware(EnsureInstallerStep::class.':migrate')
            ->name('migrate.run');

        Route::get('admin', [InstallController::class, 'adminForm'])
            ->middleware(EnsureInstallerStep::class.':admin')
            ->name('admin');
        Route::post('admin', [InstallController::class, 'adminStore'])
            ->middleware(EnsureInstallerStep::class.':admin')
            ->name('admin.store');

        Route::get('finish', [InstallController::class, 'finish'])
            ->middleware(EnsureInstallerStep::class.':finish')
            ->name('finish');
    });
