<?php

declare(strict_types=1);

use App\Modules\Workspace\Http\Controllers\OperationalWorkspaceController;
use App\Modules\Workspace\Http\Controllers\OperationalWorkspaceSwitchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('operational-workspaces')->name('operational-workspaces.')->group(function (): void {
        Route::get('/', [OperationalWorkspaceController::class, 'index'])->name('index');
        Route::get('create', [OperationalWorkspaceController::class, 'create'])->name('create');
        Route::post('/', [OperationalWorkspaceController::class, 'store'])->name('store');
        Route::post('{workspace}/switch', OperationalWorkspaceSwitchController::class)->name('switch');
    });
});
