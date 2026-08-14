<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Actions\Subscribe;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Http\Middleware\EnsureSubscriptionActive;
use App\Modules\Billing\Models\Plan;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;

/**
 * Puts every workspace that has no access-granting subscription onto a trial of
 * the signup plan.
 *
 * For data that predates plan enforcement: without this, tightening
 * {@see EnsureSubscriptionActive} would lock
 * out every pre-existing workspace. Idempotent — a workspace that already has
 * access is skipped — so it is safe to run repeatedly.
 */
class BackfillSubscriptionsCommand extends Command
{
    protected $signature = 'billing:backfill-subscriptions
        {--plan= : Plan slug to subscribe to (defaults to saas.billing.signup_plan)}
        {--dry-run : List the workspaces that would be changed without changing them}';

    protected $description = 'Give workspaces with no active plan a trial on the signup plan';

    public function handle(CurrentCompany $tenant, Subscribe $subscribe): int
    {
        $slug = (string) ($this->option('plan') ?: config('saas.billing.signup_plan'));
        $plan = Plan::query()->active()->where('slug', $slug)->first();

        if (! $plan instanceof Plan) {
            $this->error("Plan [{$slug}] not found or inactive.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $subscribed = 0;
        $skipped = 0;

        // Unscoped sweep: every workspace, regardless of the active tenant.
        Company::query()->chunkById(100, function ($companies) use (
            $tenant,
            $subscribe,
            $plan,
            $dryRun,
            &$subscribed,
            &$skipped,
        ): void {
            foreach ($companies as $company) {
                if ($company->hasActiveAccess()) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("would subscribe: {$company->name} (#{$company->id})");
                    $subscribed++;

                    continue;
                }

                try {
                    $tenant->scopeTo($company, fn () => $subscribe->handle(
                        $company,
                        $plan,
                        BillingInterval::Monthly,
                        gateway: 'manual',
                    ));
                    $subscribed++;
                } catch (BillingException $exception) {
                    $this->warn("skipped {$company->name} (#{$company->id}): {$exception->getMessage()}");
                    $skipped++;
                }
            }
        });

        $verb = $dryRun ? 'would subscribe' : 'subscribed';
        $this->info("{$verb} {$subscribed} workspace(s) to {$plan->name}; {$skipped} already had access.");

        return self::SUCCESS;
    }
}
