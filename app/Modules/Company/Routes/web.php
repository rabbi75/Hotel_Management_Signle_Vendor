<?php

declare(strict_types=1);

use App\Modules\Company\Http\Controllers\CompanyController;
use App\Modules\Company\Http\Controllers\DepartmentController;
use App\Modules\Company\Http\Controllers\InvitationAcceptanceController;
use App\Modules\Company\Http\Controllers\InvitationController;
use App\Modules\Company\Http\Controllers\MemberController;
use App\Modules\Company\Http\Controllers\OwnershipTransferController;
use App\Modules\Company\Http\Controllers\TeamController;
use App\Modules\Company\Http\Controllers\WorkspaceSwitchController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Company (workspace) routes
|------------------------------------------------------------------------------
|
| Fixed-segment routes are declared before the `companies/{company}` resource so
| that /companies/members is never swallowed by the wildcard.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {

    Route::prefix('companies')->name('companies.')->group(function (): void {

        // -- Members ---------------------------------------------------------
        Route::prefix('members')->name('members.')->group(function (): void {
            Route::get('/', [MemberController::class, 'index'])->name('index');
            Route::patch('{user:uuid}', [MemberController::class, 'update'])->name('update');
            Route::delete('{user:uuid}', [MemberController::class, 'destroy'])->name('destroy');
        });

        // -- Invitations -----------------------------------------------------
        Route::prefix('invitations')->name('invitations.')->group(function (): void {
            Route::get('/', [InvitationController::class, 'index'])->name('index');
            Route::post('/', [InvitationController::class, 'store'])->name('store');
            Route::post('{invitation}/resend', [InvitationController::class, 'resend'])->name('resend');
            Route::delete('{invitation}', [InvitationController::class, 'destroy'])->name('destroy');
        });

        // -- Ownership -------------------------------------------------------
        // Handing the workspace to someone else, like deleting it below, is out
        // of bounds for an impersonating admin.
        Route::middleware('password.confirm')->group(function (): void {
            Route::get('transfer-ownership', [OwnershipTransferController::class, 'create'])->name('transfer-ownership.create');
            Route::post('transfer-ownership', [OwnershipTransferController::class, 'store'])
                ->middleware('deny.impersonating')
                ->name('transfer-ownership.store');
        });

        // -- Workspaces ------------------------------------------------------
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('create', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');

        Route::post('{company}/switch', WorkspaceSwitchController::class)->name('switch');

        Route::get('{company}', [CompanyController::class, 'show'])->name('show');
        Route::get('{company}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '{company}', [CompanyController::class, 'update'])->name('update');

        Route::delete('{company}', [CompanyController::class, 'destroy'])
            ->middleware(['password.confirm', 'deny.impersonating'])
            ->name('destroy');
    });

    Route::resource('departments', DepartmentController::class);
    Route::resource('teams', TeamController::class);
});

/*
| Invitation acceptance is reachable by the invitee, who may not have an account
| yet; the token in the URL is the credential, and the controller resolves the
| guest case itself rather than bouncing through the auth middleware.
*/
Route::get('invitations/{invitation:token}', [InvitationAcceptanceController::class, 'show'])
    ->name('invitations.show');

Route::post('invitations/{invitation:token}', [InvitationAcceptanceController::class, 'store'])
    ->middleware('auth')
    ->name('invitations.accept');
