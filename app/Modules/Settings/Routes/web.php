<?php

declare(strict_types=1);

use App\Http\Middleware\SetCurrentCompany;
use App\Modules\Platform\Http\Middleware\ConfirmAdminPassword;
use App\Modules\Platform\Http\Middleware\EnsureActiveAdmin;
use App\Modules\Settings\Http\Controllers\AiSettingsController;
use App\Modules\Settings\Http\Controllers\ApiKeySettingsController;
use App\Modules\Settings\Http\Controllers\AppearanceSettingsController;
use App\Modules\Settings\Http\Controllers\GeneralSettingsController;
use App\Modules\Settings\Http\Controllers\LocalizationSettingsController;
use App\Modules\Settings\Http\Controllers\MailSettingsController;
use App\Modules\Settings\Http\Controllers\MaintenanceModeController;
use App\Modules\Settings\Http\Controllers\PasswordSettingsController;
use App\Modules\Settings\Http\Controllers\SecuritySettingsController;
use App\Modules\Settings\Http\Controllers\StorageSettingsController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Settings
|------------------------------------------------------------------------------
|
| Split by who the setting belongs to, not by which module implements it.
|
| Everything under `admin/settings` writes SettingsRepository's *system* scope:
| SMTP credentials, S3 and R2 keys, the Stripe and OpenAI secrets, the password
| policy, and maintenance mode for the entire installation. Those configure the
| product, so they sit on the `admin` guard behind `platform.settings.*`.
|
| What stays on the tenant side is only what genuinely belongs to the person
| signed in — their own password screen. A workspace member configures their
| workspace; they do not configure the product it runs on.
|
*/

// -- The operator's installation settings -------------------------------------
Route::prefix('admin/settings')
    ->name('admin.settings.')
    ->middleware(['auth:admin', EnsureActiveAdmin::class])
    ->withoutMiddleware(SetCurrentCompany::class)
    ->group(function (): void {
        Route::get('/', [GeneralSettingsController::class, 'index'])->name('index');
        Route::put('general', [GeneralSettingsController::class, 'update'])->name('general.update');

        Route::get('localization', [LocalizationSettingsController::class, 'index'])->name('localization.index');
        Route::put('localization', [LocalizationSettingsController::class, 'update'])->name('localization.update');

        Route::get('mail', [MailSettingsController::class, 'index'])->name('mail.index');
        Route::put('mail', [MailSettingsController::class, 'update'])->name('mail.update');
        Route::post('mail/test', [MailSettingsController::class, 'sendTest'])
            ->middleware('throttle:auth')
            ->name('mail.test');

        Route::get('storage', [StorageSettingsController::class, 'index'])->name('storage.index');
        Route::put('storage', [StorageSettingsController::class, 'update'])->name('storage.update');

        Route::get('appearance', [AppearanceSettingsController::class, 'index'])->name('appearance.index');
        Route::put('appearance', [AppearanceSettingsController::class, 'update'])->name('appearance.update');

        Route::get('maintenance', [MaintenanceModeController::class, 'index'])->name('maintenance.index');
        Route::post('maintenance', [MaintenanceModeController::class, 'enable'])->name('maintenance.enable');
        Route::delete('maintenance', [MaintenanceModeController::class, 'disable'])->name('maintenance.disable');

        // Credentials and authentication policy are the two panels where a
        // stolen session does the most damage, so both re-challenge for the
        // password before they can even be read.
        Route::middleware(ConfirmAdminPassword::class)->group(function (): void {
            Route::get('api-keys', [ApiKeySettingsController::class, 'index'])->name('api_keys.index');
            Route::put('api-keys', [ApiKeySettingsController::class, 'update'])->name('api_keys.update');

            Route::get('ai', [AiSettingsController::class, 'index'])->name('ai.index');
            Route::put('ai', [AiSettingsController::class, 'update'])->name('ai.update');
            Route::post('ai/{provider}/test', [AiSettingsController::class, 'test'])
                ->middleware('throttle:auth')
                ->name('ai.test');

            Route::get('security', [SecuritySettingsController::class, 'index'])->name('security.index');
            Route::put('security', [SecuritySettingsController::class, 'update'])->name('security.update');
        });
    });

// -- The signed-in member's own account ---------------------------------------
Route::middleware(['auth', 'verified'])
    ->prefix('settings')
    ->name('settings.')
    ->group(function (): void {
        // Kept as a redirect rather than dropped with the panels it used to
        // list. `/settings` is a URL people have bookmarked and the account menu
        // still reaches for, and an unclaimed path falls through to the CMS
        // catch-all — so removing it would turn "Settings" into a page lookup.
        Route::redirect('/', '/profile')->name('index');

        // Fortify owns `PUT user/password`; it just never ships a screen for it.
        Route::get('password', [PasswordSettingsController::class, 'edit'])->name('password.edit');
    });
