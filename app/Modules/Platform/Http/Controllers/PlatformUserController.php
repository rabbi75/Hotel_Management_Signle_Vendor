<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Actions\RestoreUser;
use App\Modules\User\Actions\SuspendUser;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every user in the installation, regardless of workspace.
 *
 * The tenant-facing users screen scopes to the active workspace's membership
 * pivot ({@see UserController::query()});
 * this one deliberately does not, which is the whole point of it.
 */
class PlatformUserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.users.manage') ?? true, 403);

        return Inertia::render('admin/users/index', [
            'table' => $this->table($request)->toArray(),
            'statuses' => UserStatus::options(),
        ]);
    }

    public function suspend(Request $request, User $user, SuspendUser $suspend): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.users.manage') ?? true, 403);
        abort_if($request->user('admin')?->is($user) ?? false, 403, __('You cannot suspend yourself.'));
        abort_if($user->isSuperAdmin(), 403, __('A super admin cannot be suspended.'));

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $suspend->handle($user, $validated['reason'] ?? null);

        return back()->with('success', __('User suspended.'));
    }

    public function restore(Request $request, User $user, RestoreUser $restore): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.users.manage') ?? true, 403);

        $restore->handle($user);

        return back()->with('success', __('User restored.'));
    }

    /**
     * @return TableBuilder<User>
     */
    protected function table(Request $request): TableBuilder
    {
        return TableBuilder::for($this->query(), $request)
            ->columns([
                Column::make('name')->searchable()->sortable()->locked(),
                Column::make('email')->searchable()->sortable(),
                Column::make('workspaces', __('Workspaces')),
                Column::make('status')->sortable(),
                Column::make('last_login_at', __('Last login'))->sortable(),
                Column::make('created_at', __('Registered'))->sortable(),
            ])
            ->filters([
                Filter::make('status')->fromEnum(UserStatus::class)->multiple(),
                Filter::make('created_at', __('Registered'))->dateRange(),
            ])
            ->defaultSort('created_at')
            ->transform(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatarUrl(),
                'initials' => $user->initials(),
                'status' => $user->status->value,
                'is_super_admin' => $user->isSuperAdmin(),
                'workspaces' => $user->companies->map(static fn ($company): array => [
                    'uuid' => $company->uuid,
                    'name' => $company->name,
                ])->all(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return Builder<User>
     */
    protected function query(): Builder
    {
        return User::query()->with(['companies', 'roles']);
    }
}
