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
 * Creates a tenant account with predictable credentials.
 *
 * DemoDataSeeder fabricates its members through the factory, so their emails are
 * random and nobody can log in as one without querying the database first. This
 * seeder fills that gap: an ordinary workspace owner — no super-admin role — for
 * checking what a customer actually sees.
 *
 * Idempotent: re-running never duplicates the account, and never resets the
 * password of an account that already exists.
 */
class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('saas.seed.demo_tenant_email');
        $password = (string) config('saas.seed.demo_tenant_password');

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Demo tenant {$email} already exists — skipping.");

            return;
        }

        DB::transaction(function () use ($email, $password): void {
            $user = User::create([
                'uuid' => (string) Str::ulid(),
                'first_name' => 'Demo',
                'last_name' => 'Tenant',
                'name' => 'Demo Tenant',
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
                'timezone' => config('saas.defaults.timezone'),
                'locale' => config('saas.defaults.locale'),
                'theme' => Theme::System,
            ]);

            $company = Company::create([
                'name' => (string) config('saas.seed.demo_tenant_workspace'),
                'owner_id' => $user->id,
                'email' => $email,
                'timezone' => config('saas.defaults.timezone'),
                'currency' => config('saas.defaults.currency'),
                'locale' => config('saas.defaults.locale'),
            ]);

            $company->members()->attach($user->id, [
                'role' => CompanyRole::Owner->value,
                'joined_at' => now(),
            ]);

            $user->forceFill(['current_company_id' => $company->id])->save();
            $user->assignRole('admin');

            $this->subscribeToPro($company);
        });

        $this->command?->info("Created demo tenant {$email}.");
    }

    /**
     * Put the demo workspace on an active Pro subscription so the tenant panel
     * demonstrates a paying customer. Created directly on the `manual` gateway —
     * no payment processor is involved in a seed — and unscoped, because no
     * tenant is resolved during seeding.
     */
    protected function subscribeToPro(Company $company): void
    {
        $plan = Plan::query()->where('slug', 'pro')->first();

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
