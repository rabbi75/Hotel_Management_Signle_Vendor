<?php

declare(strict_types=1);

namespace App\Modules\Role\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Idempotent: delegates to `permission:sync`, which only ever adds, so seeding
 * an already-populated database is a no-op rather than a duplicate-key error.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permission:sync');
    }
}
