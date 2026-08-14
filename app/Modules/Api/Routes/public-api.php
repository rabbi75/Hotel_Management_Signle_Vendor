<?php

declare(strict_types=1);

use App\Modules\Api\Http\Controllers\V1\CompanyController;
use App\Modules\Api\Http\Controllers\V1\DepartmentController;
use App\Modules\Api\Http\Controllers\V1\OpenApiController;
use App\Modules\Api\Http\Controllers\V1\UserController;
use App\Modules\Api\Http\Middleware\ResolveTokenCompany;
use App\Modules\Company\Models\Department;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Public REST API (v1)
|------------------------------------------------------------------------------
|
| Registered by ApiServiceProvider under config('saas.api.prefix'). Every route
| here is Sanctum authenticated, bound to the workspace stamped on the token,
| rate limited, and logged.
|
| Endpoints belonging to optional modules are guarded by class_exists so this
| file stays valid in a build where that module was removed.
|
*/

Route::get('openapi.json', OpenApiController::class)
    ->withoutMiddleware([ResolveTokenCompany::class])
    ->name('openapi');

Route::prefix('users')->name('users.')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('{uuid}', [UserController::class, 'show'])->name('show');
    Route::match(['put', 'patch'], '{uuid}', [UserController::class, 'update'])->name('update');
    Route::delete('{uuid}', [UserController::class, 'destroy'])->name('destroy');
});

Route::prefix('workspaces')->name('workspaces.')->group(function (): void {
    Route::get('/', [CompanyController::class, 'index'])->name('index');
    Route::get('{uuid}', [CompanyController::class, 'show'])->name('show');
});

if (class_exists(Department::class)) {
    Route::prefix('departments')->name('departments.')->group(function (): void {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::get('{department}', [DepartmentController::class, 'show'])->name('show');
    });
}
