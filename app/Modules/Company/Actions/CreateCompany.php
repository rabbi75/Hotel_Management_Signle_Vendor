<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Billing\Actions\Subscribe;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Company\DTOs\CompanyData;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Events\CompanyCreated;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Actions\CreateDefaultWorkspace;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates a workspace and enrols its owner.
 *
 * The owner is added to company_user in the same transaction: a workspace whose
 * creator is not a member would be invisible to them the moment tenant scoping
 * applied.
 */
class CreateCompany
{
    public function __construct(
        protected CurrentCompany $tenant,
        protected Subscribe $subscribe,
        protected CreateDefaultWorkspace $createDefaultWorkspace,
    ) {}

    public function handle(CompanyData $data, User $owner): Company
    {
        $company = DB::transaction(function () use ($data, $owner): Company {
            $company = new Company;

            $company->fill($data->toAttributes());
            $company->owner_id = $owner->id;
            $company->trial_ends_at = config('saas.billing.trial_days') > 0
                ? now()->addDays((int) config('saas.billing.trial_days'))
                : null;

            $company->save();

            $company->members()->attach($owner->id, [
                'role' => CompanyRole::Owner->value,
                'joined_at' => now(),
            ]);

            // The seeded roles are global (guard `web`), so the owner is granted
            // the admin role here rather than a per-workspace role record.
            $owner->assignRole('admin');
            $owner->flushPermissionCache();

            event(new CompanyCreated($company, $owner));

            $workspace = $this->createDefaultWorkspace->handle($company, $owner);

            $owner->forceFill([
                'current_company_id' => $company->id,
                'current_workspace_id' => $workspace->id,
            ])->saveQuietly();

            return $company;
        });

        $this->startSignupSubscription($company);

        return $company;
    }

    /**
     * Put a new workspace on a trial of the configured signup plan.
     *
     * Runs after the workspace transaction commits, not inside it: a subscription
     * that fails to start (misconfigured plan, gateway hiccup) must not roll back
     * the workspace itself — the owner still gets their workspace and is caught by
     * the plan picker on first entry. Deliberately best-effort and logged.
     */
    protected function startSignupSubscription(Company $company): void
    {
        if (! (bool) config('saas.billing.enabled', false)) {
            return;
        }

        $slug = (string) config('saas.billing.signup_plan');

        if ($slug === '') {
            return;
        }

        $plan = Plan::query()->active()->where('slug', $slug)->first();

        if (! $plan instanceof Plan) {
            Log::warning('Signup plan not found; workspace created without a subscription.', [
                'company_id' => $company->id,
                'signup_plan' => $slug,
            ]);

            return;
        }

        try {
            // The signup plan's own trial_days drives the trial; the manual
            // gateway records it without contacting a processor.
            $this->tenant->scopeTo($company, fn () => $this->subscribe->handle(
                $company,
                $plan,
                BillingInterval::Monthly,
                gateway: 'manual',
            ));
        } catch (BillingException|Throwable $exception) {
            Log::warning('Failed to start signup subscription.', [
                'company_id' => $company->id,
                'signup_plan' => $slug,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
