<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Removes the tenant-side `settings.*` permissions.
 *
 * Those panels wrote the installation's own configuration — SMTP credentials,
 * object-storage keys, the Stripe and OpenAI secrets, the password policy and
 * maintenance mode — while the workspace `admin` role, which CreateCompany
 * assigns to whoever signs up, held all seven. They now live on the `admin`
 * guard as `platform.settings.*`.
 *
 * Dropping them from config/permissions.php is not enough on an installation
 * that has already been seeded: the permission rows and their role assignments
 * are database state, and `permission:sync` adds what is declared without
 * revoking what no longer is. Left in place they are dormant rather than
 * harmless — any future route or gate named `settings.*` would silently hand
 * every workspace owner the grant back.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const REVOKED = [
        'settings.view',
        'settings.general.update',
        'settings.mail.update',
        'settings.storage.update',
        'settings.security.update',
        'settings.api_keys.manage',
        'settings.maintenance.toggle',
    ];

    public function up(): void
    {
        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', self::REVOKED)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Both pivots: a permission may have been granted to a role or straight
        // to a user, and only clearing one would leave the other dangling.
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Deliberately irreversible.
     *
     * Rolling back would recreate a privilege escalation, and the panels these
     * guarded no longer exist on the tenant guard for them to guard.
     */
    public function down(): void
    {
        //
    }
};
