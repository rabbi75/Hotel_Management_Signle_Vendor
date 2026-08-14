<?php

declare(strict_types=1);

use App\Modules\Role\Http\Controllers\PermissionMatrixController;
use App\Modules\Role\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {

    Route::get('roles/permissions', [PermissionMatrixController::class, 'show'])->name('roles.permissions.show');
    Route::put('roles/permissions', [PermissionMatrixController::class, 'update'])->name('roles.permissions.update');

    Route::resource('roles', RoleController::class)->except(['destroy']);

    // Deleting a role revokes capabilities from live accounts, so it is treated
    // as destructive and re-prompts for the password.
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('password.confirm')
        ->name('roles.destroy');
});
