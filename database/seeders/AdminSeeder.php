<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Platform\Database\Seeders\AdminRolePermissionSeeder;
use App\Modules\Platform\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Creates the first platform administrator.
 *
 * Idempotent: re-running never duplicates the account and never resets the
 * password of one that already exists. Ensures the admin-guard roles exist
 * first, so the account can be granted super-admin.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminRolePermissionSeeder::class);

        $email = (string) config('saas.admin.seed_email');

        if (Admin::query()->where('email', $email)->exists()) {
            $this->command?->info("Platform admin {$email} already exists — skipping.");

            return;
        }

        $admin = Admin::create([
            'name' => (string) config('saas.admin.seed_name'),
            'email' => $email,
            'password' => (string) config('saas.admin.seed_password'),
            'status' => 'active',
        ]);

        $admin->assignRole('super-admin');

        $this->command?->info("Created platform admin {$email}.");
    }
}
