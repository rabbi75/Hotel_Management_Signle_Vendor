<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Controllers\LoginHistoryController;
use App\Modules\Auth\Http\Controllers\LogoutController;
use App\Modules\Auth\Http\Controllers\SessionController;
use App\Modules\Auth\Http\Controllers\SocialAuthController;
use App\Modules\Auth\Http\Controllers\TwoFactorController;
use App\Modules\Auth\Services\SocialProviderRegistry;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Auth routes
|------------------------------------------------------------------------------
|
| Fortify owns login, registration, password reset and the two-factor
| challenge. What lives here is the authenticated account-security surface it
| does not provide: the 2FA settings screen, session management, the user's own
| sign-in history, and social sign-in.
|
*/

// Declared after Fortify's so it takes over POST /logout and can close the
// login-history row before the session is destroyed.
Route::post('logout', LogoutController::class)->middleware('auth')->name('logout');

Route::middleware(['auth'])->prefix('settings')->name('settings.')->group(function (): void {

    // Credential changes are blocked while impersonating: an admin must never be
    // able to alter the customer's second factor or recovery codes from inside
    // their session.
    Route::prefix('two-factor')->name('two-factor.')->group(function (): void {
        Route::get('/', [TwoFactorController::class, 'show'])->name('show');

        Route::middleware(['password.confirm', 'deny.impersonating'])->group(function (): void {
            Route::post('/', [TwoFactorController::class, 'store'])->name('store');
            Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
            Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('recovery-codes');
            Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
            Route::delete('/', [TwoFactorController::class, 'destroy'])->name('destroy');
        });
    });

    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');

    Route::middleware('password.confirm')->group(function (): void {
        Route::delete('sessions/others', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
        Route::delete('sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
    });

    Route::get('login-history', [LoginHistoryController::class, 'index'])->name('login-history');
});

/*
| Social routes exist only for providers that are actually configured, so an
| unconfigured button can never be reached even by typing the URL.
*/
$socials = array_keys(app(SocialProviderRegistry::class)->enabled());

if ($socials !== []) {
    Route::prefix('auth')->name('social.')->group(function () use ($socials): void {
        Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->whereIn('provider', $socials)
            ->name('redirect');

        Route::get('{provider}/callback', [SocialAuthController::class, 'callback'])
            ->whereIn('provider', $socials)
            ->name('callback');
    });
}
