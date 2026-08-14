<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Department;
use App\Modules\Company\Models\Team;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Sample content so a fresh install has something to look at: two workspaces,
 * a spread of members, departments and teams.
 *
 * Local environment only — see DatabaseSeeder.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = app(CurrentCompany::class);

        Company::query()->take(2)->get()->each(function (Company $company) use ($tenant): void {
            $tenant->set($company);

            $departments = collect(['Engineering', 'Sales', 'Support', 'Finance'])
                ->map(fn (string $name): Department => Department::create([
                    'name' => $name,
                    'description' => "The {$name} department.",
                ]));

            $departments->each(function (Department $department): void {
                Team::create([
                    'department_id' => $department->id,
                    'name' => "{$department->name} Core",
                    'color' => fake()->hexColor(),
                ]);
            });

            User::factory()
                ->count(24)
                ->create()
                ->each(function (User $user, int $index) use ($company, $departments): void {
                    $company->members()->attach($user->id, [
                        'role' => match (true) {
                            $index < 2 => CompanyRole::Admin->value,
                            $index < 18 => CompanyRole::Member->value,
                            default => CompanyRole::Guest->value,
                        },
                        'department_id' => $departments->random()->id,
                        'job_title' => fake()->jobTitle(),
                        'joined_at' => now()->subDays(random_int(1, 400)),
                    ]);

                    $user->assignRole($index < 2 ? 'admin' : 'member');
                    $user->forceFill([
                        'current_company_id' => $company->id,
                        'last_login_at' => now()->subDays(random_int(0, 30)),
                    ])->save();
                });
        });

        $tenant->forget();

        $this->command?->info('Seeded demo departments, teams and members.');
    }
}
