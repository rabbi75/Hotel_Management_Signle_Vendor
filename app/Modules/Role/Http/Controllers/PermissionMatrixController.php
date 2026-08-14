<?php

declare(strict_types=1);

namespace App\Modules\Role\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Role\Actions\SyncRolePermissions;
use App\Modules\Role\Http\Requests\UpdatePermissionMatrixRequest;
use App\Modules\Role\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The permission registry crossed with every editable role.
 *
 * The grid is rendered from config/permissions.php rather than from the
 * `permissions` table so a permission that has not been synced yet is still
 * visible — and grantable — instead of quietly missing from the UI.
 */
class PermissionMatrixController extends Controller
{
    public function show(): Response
    {
        $this->authorize('updatePermissions', Role::class);

        /** @var Collection<int, Role> $roles */
        $roles = Role::query()->assignable()->with('permissions')->orderBy('name')->get();

        return Inertia::render('roles/permissions', [
            'groups' => $this->groups(),
            'roles' => $roles->map(static fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label(),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function update(UpdatePermissionMatrixRequest $request, SyncRolePermissions $sync): RedirectResponse
    {
        $changed = $sync->handle($request->matrix());

        return back()->with('success', trans_choice(
            '{0}No permission changes were needed.|[1,*]Updated permissions for :count role(s).',
            $changed,
            ['count' => $changed],
        ));
    }

    /**
     * @return list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>
     */
    protected function groups(): array
    {
        /** @var array<string, array{label: string, permissions: array<string, string>}> $groups */
        $groups = config('permissions.groups', []);

        $result = [];

        foreach ($groups as $key => $group) {
            $result[] = [
                'key' => (string) $key,
                'label' => $group['label'],
                'permissions' => array_map(
                    static fn (string $name, string $label): array => ['name' => $name, 'label' => $label],
                    array_map(strval(...), array_keys($group['permissions'])),
                    array_values($group['permissions']),
                ),
            ];
        }

        return $result;
    }
}
