<?php

declare(strict_types=1);

namespace App\Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The operator console's roles and permissions, on the `admin` guard.
 *
 * The console needs only the `platform.*` permissions, and its own two roles —
 * quite unlike the tenant guard's catalogue — so this does not reuse
 * `permission:sync` (which would create every declared permission and every web
 * role on the admin guard). Idempotent: everything is firstOrCreate + sync.
 */
class AdminRolePermissionSeeder extends Seeder
{
    protected const GUARD = 'admin';

    /**
     * The support role: read the console and step into a workspace, nothing that
     * changes a tenant's billing, status, or the admin roster.
     *
     * @var list<string>
     */
    protected array $support = [
        'platform.access',
        'platform.metrics.view',
        'platform.tenants.view',
        'platform.tenants.impersonate',
        'platform.users.manage',

        // Support may read the money but never move it: refunds, retries and
        // grace extensions stay with an admin who holds platform.billing.manage.
        'platform.revenue.view',
        'platform.invoices.view',

        // Support reads the configuration to diagnose, but changes none of it:
        // the write permissions cover credentials and the maintenance switch.
        'platform.settings.view',
        'platform.audit.view',
        'platform.tickets.view',
        'platform.tickets.manage',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $permissions = $this->platformPermissions();

            foreach ($permissions as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
            }

            // super-admin holds every platform permission; the Gate::before in
            // AuthServiceProvider also lets it bypass checks, but granting the
            // rows keeps role-permission listings honest.
            Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => self::GUARD])
                ->syncPermissions($permissions);

            Role::firstOrCreate(['name' => 'support', 'guard_name' => self::GUARD])
                ->syncPermissions(array_values(array_intersect($permissions, $this->support)));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    protected function platformPermissions(): array
    {
        /** @var array<string, string> $group */
        $group = config('permissions.groups.platform.permissions', []);

        return array_keys($group);
    }
}
