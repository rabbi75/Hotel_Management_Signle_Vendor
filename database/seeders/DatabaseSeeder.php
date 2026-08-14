<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CMS\Database\Seeders\LandingPageSeeder;
use App\Modules\Role\Database\Seeders\RolePermissionSeeder;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Database\Seeder;

/**
 * Entry point for `php artisan db:seed`.
 *
 * Order matters: permissions and settings must exist before any account is
 * created, or the first user cannot be granted a role. Module seeders are
 * resolved by class name so a module can be dropped from the kit without
 * breaking the seed.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seeders that must always run, in dependency order.
     *
     * @var list<class-string<Seeder>>
     */
    protected array $core = [
        RolePermissionSeeder::class,
        SettingsSeeder::class,
        PlanSeeder::class,
        AdminSeeder::class,

        // The public landing page. Runs on every install, production included:
        // it seeds content, not accounts, and without it `/` has nothing to
        // render but the starter kit's fallback screen.
        LandingPageSeeder::class,
    ];

    public function run(): void
    {
        foreach ($this->core as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }

        $this->call(AdminUserSeeder::class);

        // Sample content is opt-in: a production deploy runs `db:seed` too and
        // must not fabricate accounts there.
        if (app()->environment('local') && (bool) config('saas.seed.demo_data')) {
            // Before DemoDataSeeder, which populates the first two workspaces:
            // the demo tenant is the second, so it gets departments and members
            // rather than an empty shell.
            $this->call(DemoTenantSeeder::class);
            $this->call(DemoDataSeeder::class);
        }
    }
}
