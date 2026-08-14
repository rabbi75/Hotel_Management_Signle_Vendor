<?php

declare(strict_types=1);

namespace App\Modules\Role\Console;

use App\Modules\Role\Models\Permission;
use App\Modules\Role\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reconciles the database with config/permissions.php.
 *
 * Safe to run on every deploy: it only ever adds, and orphans are reported
 * rather than removed unless --prune is passed, because deleting a permission
 * silently revokes it from every role that still references it.
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'permission:sync
        {--prune : Delete permissions that are no longer declared in the registry}
        {--guard= : Guard name to sync (defaults to the application guard)}';

    protected $description = 'Create the permissions and roles declared in config/permissions.php';

    public function handle(PermissionRegistrar $registrar): int
    {
        $guard = (string) ($this->option('guard') ?? config('auth.defaults.guard', 'web'));

        $declared = Permission::declared();

        if ($declared === []) {
            $this->components->error('No permissions are declared in config/permissions.php.');

            return self::FAILURE;
        }

        $created = DB::transaction(fn (): int => $this->syncPermissions($declared, $guard));
        $roles = DB::transaction(fn (): int => $this->syncRoles($guard));

        $orphans = Permission::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', $declared)
            ->pluck('name')
            ->all();

        $this->reportOrphans($orphans, $guard);

        $registrar->forgetCachedPermissions();

        $this->components->info(sprintf(
            '%d permission(s) created, %d role(s) synced, %d orphan(s) found.',
            $created,
            $roles,
            count($orphans),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $declared
     */
    protected function syncPermissions(array $declared, string $guard): int
    {
        $existing = Permission::query()
            ->where('guard_name', $guard)
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff($declared, $existing));

        foreach ($missing as $name) {
            Permission::create(['name' => $name, 'guard_name' => $guard]);
            $this->components->twoColumnDetail($name, '<fg=green>created</>');
        }

        return count($missing);
    }

    protected function syncRoles(string $guard): int
    {
        /** @var array<string, array{permissions?: list<string>}> $declared */
        $declared = config('permissions.roles', []);

        foreach ($declared as $name => $definition) {
            $role = Role::firstOrCreate(['name' => (string) $name, 'guard_name' => $guard]);

            $permissions = $this->expand($definition['permissions'] ?? [], $guard);

            $role->syncPermissions($permissions);

            $this->components->twoColumnDetail(
                (string) $name,
                sprintf('<fg=cyan>%d permission(s)</>', count($permissions)),
            );
        }

        return count($declared);
    }

    /**
     * Expands `group.*` wildcards into the concrete permission names that exist
     * for the guard.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     */
    protected function expand(array $patterns, string $guard): array
    {
        $all = Permission::query()->where('guard_name', $guard)->pluck('name')->all();
        $resolved = [];

        foreach ($patterns as $pattern) {
            if (! str_contains($pattern, '*')) {
                $resolved[] = $pattern;

                continue;
            }

            $prefix = str($pattern)->before('*')->toString();

            foreach ($all as $name) {
                if (str_starts_with((string) $name, $prefix)) {
                    $resolved[] = (string) $name;
                }
            }
        }

        return array_values(array_unique(array_intersect($resolved, array_map(strval(...), $all))));
    }

    /**
     * @param  list<string>  $orphans
     */
    protected function reportOrphans(array $orphans, string $guard): void
    {
        if ($orphans === []) {
            return;
        }

        foreach ($orphans as $name) {
            $this->components->twoColumnDetail((string) $name, '<fg=yellow>orphaned</>');
        }

        if (! $this->option('prune')) {
            $this->components->warn('Run with --prune to delete the orphaned permissions above.');

            return;
        }

        Permission::query()->where('guard_name', $guard)->whereIn('name', $orphans)->delete();

        $this->components->info(sprintf('%d orphaned permission(s) deleted.', count($orphans)));
    }
}
