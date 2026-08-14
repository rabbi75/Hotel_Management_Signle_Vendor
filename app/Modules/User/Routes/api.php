<?php

declare(strict_types=1);

use App\Modules\User\Http\Resources\AuthenticatedUserResource;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Http\Resources\UserSummaryResource;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Read-only user endpoints. Writes stay on the web routes so they keep session
| confirmation, CSRF and the Inertia redirect contract.
*/

Route::middleware('auth:sanctum')->group(function (): void {

    Route::get('me', fn (Request $request) => new AuthenticatedUserResource($request->user()))
        ->name('users.me');

    Route::middleware('permission:users.view')->group(function (): void {

        Route::get('users', function (Request $request) {
            $search = $request->string('search')->toString();

            return UserSummaryResource::collection(
                User::query()
                    ->whereHas('companies', static fn (Builder $query) => $query->whereKey(current_company_id()))
                    ->when($search !== '', static fn (Builder $query) => $query->where(
                        static fn (Builder $inner) => $inner
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"),
                    ))
                    ->orderBy('name')
                    ->paginate((int) config('saas.tables.per_page')),
            );
        })->name('users.index');

        Route::get('users/{user}', function (Request $request, User $user) {
            abort_unless($request->user()?->can('view', $user) ?? false, 403);

            return new UserResource($user->load('roles'));
        })->name('users.show');
    });
});
