<?php

declare(strict_types=1);

use App\Modules\User\Http\Controllers\ProfileController;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\User\Http\Controllers\UserImpersonationController;
use App\Modules\User\Http\Controllers\UserStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {

    Route::prefix('profile')->name('profile.')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('preferences', [ProfileController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('avatar', [ProfileController::class, 'storeAvatar'])->name('avatar.store');
        Route::delete('avatar', [ProfileController::class, 'destroyAvatar'])->name('avatar.destroy');

        // Irreversible: the password prompt is the last line of defence against
        // a hijacked but unauthenticated-in-the-moment session. An impersonating
        // admin is blocked outright — deleting a customer's account from inside
        // their own session is never a support action.
        Route::delete('/', [ProfileController::class, 'destroy'])
            ->middleware(['password.confirm', 'deny.impersonating'])
            ->name('destroy');
    });

    Route::get('users/export', [UserController::class, 'export'])
        ->middleware('throttle:export')
        ->name('users.export');

    Route::resource('users', UserController::class)->except(['destroy']);

    Route::post('users/{user}/suspend', [UserStatusController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/restore', [UserStatusController::class, 'restore'])
        ->withTrashed()
        ->name('users.restore');

    Route::post('users/{user}/impersonate', [UserImpersonationController::class, 'store'])->name('users.impersonate');
    Route::delete('impersonate', [UserImpersonationController::class, 'destroy'])->name('users.impersonate.stop');

    Route::middleware('password.confirm')->group(function (): void {
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::delete('users/{user}/force', [UserStatusController::class, 'forceDelete'])
            ->withTrashed()
            ->name('users.force-delete');
    });
});
