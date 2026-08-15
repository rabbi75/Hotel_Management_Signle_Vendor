<?php

declare(strict_types=1);

namespace App\Modules\Role\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Role\Actions\CreateRole;
use App\Modules\Role\Actions\DeleteRole;
use App\Modules\Role\Actions\UpdateRole;
use App\Modules\Role\Http\Requests\StoreRoleRequest;
use App\Modules\Role\Http\Requests\UpdateRoleRequest;
use App\Modules\Role\Http\Resources\RoleResource;
use App\Modules\Role\Models\Role;
use App\Support\DataTable\Column;
use App\Support\DataTable\TableBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $table = TableBuilder::for($this->query(), $request)
            ->columns([
                Column::make('name')->searchable()->sortable()->locked(),
                Column::make('guard_name', __('Guard'))->sortable(),
                Column::make('users_count', __('Users'))->align('right'),
                Column::make('permissions_count', __('Permissions'))->align('right'),
                Column::make('created_at')->sortable()->hidden(),
            ])
            ->defaultSort('name', 'desc')
            ->transform(static fn (Role $role): array => (new RoleResource($role))->resolve())
            ->toArray();

        return Inertia::render('roles/index', [
            'table' => $table,
            'can' => [
                'create' => $request->user()?->can('create', Role::class) ?? false,
                'update_permissions' => $request->user()?->can('updatePermissions', Role::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('roles/create', [
            'groups' => config('permissions.groups'),
        ]);
    }

    public function store(StoreRoleRequest $request, CreateRole $createRole): RedirectResponse
    {
        /** @var list<string> $permissions */
        $permissions = array_values((array) $request->validated('permissions', []));

        $role = $createRole->handle((string) $request->string('name'), $permissions, $request->guard());

        return redirect()
            ->route('roles.edit', $role)
            ->with('success', __('Role created.'));
    }

    public function show(Role $role): Response
    {
        $this->authorize('view', $role);

        $role->load('permissions')->loadCount('users');

        return Inertia::render('roles/show', [
            'role' => (new RoleResource($role))->resolve(),
            'groups' => config('permissions.groups'),
        ]);
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        $role->load('permissions')->loadCount('users');

        return Inertia::render('roles/edit', [
            'role' => (new RoleResource($role))->resolve(),
            'groups' => config('permissions.groups'),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $updateRole): RedirectResponse
    {
        /** @var list<string>|null $permissions */
        $permissions = $request->has('permissions')
            ? array_values((array) $request->validated('permissions', []))
            : null;

        $updateRole->handle($role, (string) $request->string('name'), $permissions);

        return redirect()
            ->route('roles.index')
            ->with('success', __('Role updated.'));
    }

    public function destroy(Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        $this->authorize('delete', $role);

        $deleteRole->handle($role);

        return redirect()
            ->route('roles.index')
            ->with('success', __('Role deleted.'));
    }

    /**
     * @return Builder<Role>
     */
    protected function query(): Builder
    {
        return Role::query()->withCount(['users', 'permissions']);
    }
}
