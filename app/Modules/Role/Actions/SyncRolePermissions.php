<?php

declare(strict_types=1);

namespace App\Modules\Role\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Role\Actions\Concerns\FlushesPermissionCaches;
use App\Modules\Role\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Persists a whole role→permission grid in one transaction.
 *
 * The matrix editor submits every row it displayed, so a partial write would
 * leave the grid the administrator is looking at out of step with what was
 * stored; either the entire grid lands or none of it does.
 */
class SyncRolePermissions
{
    use FlushesPermissionCaches;

    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  array<int, list<string>>  $matrix  Permission names keyed by role id.
     * @return int Number of roles whose permission set actually changed.
     */
    public function handle(array $matrix): int
    {
        return DB::transaction(function () use ($matrix): int {
            /** @var Collection<int, Role> $roles */
            $roles = Role::query()
                ->assignable()
                ->with('permissions')
                ->whereIn('id', array_keys($matrix))
                ->get();

            $changed = [];

            foreach ($roles as $role) {
                $desired = $matrix[$role->id] ?? [];
                $current = $role->permissions->pluck('name')->all();

                sort($desired);
                sort($current);

                if ($desired === $current) {
                    continue;
                }

                $role->syncPermissions($desired);
                $this->flushCachesForRole($role);

                $changed[$role->name] = ['from' => $current, 'to' => $desired];
            }

            if ($changed !== []) {
                $this->security->log(
                    SecurityEvent::PermissionsChanged,
                    auth()->user(),
                    __('Updated the permission matrix for :count role(s).', ['count' => count($changed)]),
                    ['roles' => $changed],
                );
            }

            return count($changed);
        });
    }
}
