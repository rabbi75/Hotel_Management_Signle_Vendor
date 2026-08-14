<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Models\User;
use App\Support\Enums\Theme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the first super-admin account and its workspace.
 *
 * Idempotent: re-running never duplicates the account, and never resets the
 * password of an account that already exists.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('saas.seed.admin_email');
        $password = (string) config('saas.seed.admin_password');

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Admin user {$email} already exists — skipping.");

            return;
        }

        DB::transaction(function () use ($email, $password): void {
            $admin = User::create([
                'uuid' => (string) Str::ulid(),
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'name' => 'System Administrator',
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
                'timezone' => config('saas.defaults.timezone'),
                'locale' => config('saas.defaults.locale'),
                'theme' => Theme::System,
            ]);

            $company = Company::create([
                'name' => (string) config('saas.seed.admin_workspace'),
                'owner_id' => $admin->id,
                'email' => $email,
                'timezone' => config('saas.defaults.timezone'),
                'currency' => config('saas.defaults.currency'),
                'locale' => config('saas.defaults.locale'),
            ]);

            $company->members()->attach($admin->id, [
                'role' => CompanyRole::Owner->value,
                'joined_at' => now(),
            ]);

            $admin->forceFill(['current_company_id' => $company->id])->save();
            $admin->assignRole((string) config('permissions.super_admin_role', 'super-admin'));

            $this->subscribeToTopPlan($company);
        });

        $this->command?->info("Created super admin {$email}.");
    }

    /**
     * The operator's own workspace runs on the top plan, active — never a trial,
     * never at risk of being locked out by the plan gate. Skips quietly if the
     * catalogue has not been seeded.
     */
    protected function subscribeToTopPlan(Company $company): void
    {
        $plan = Plan::query()->orderByDesc('sort')->first();

        if (! $plan instanceof Plan) {
            return;
        }

        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'gateway' => 'manual',
            'status' => SubscriptionStatus::Active,
            'interval' => BillingInterval::Monthly,
            'quantity' => 1,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'dunning_attempts' => 0,
        ]);

        $subscription->company_id = $company->id;
        $subscription->saveQuietly();
    }
}
