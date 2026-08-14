<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Actions\CancelSubscription;
use App\Modules\Billing\Actions\Subscribe;
use App\Modules\Billing\Actions\SwapPlan;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * An operator changing what a workspace is entitled to.
 *
 * Everything runs inside {@see CurrentCompany::scopeTo()} so the Billing
 * actions — which assume a resolved tenant — work untouched, and every change
 * is granted through the `manual` gateway: assigning a plan from the admin
 * panel is an accounting decision, not a charge.
 */
class TenantSubscriptionController extends Controller
{
    protected const GATEWAY = 'manual';

    public function __construct(
        protected CurrentCompany $tenant,
        protected SecurityLogger $security,
    ) {}

    /**
     * Put the workspace on a plan: swap the live subscription, or start one.
     */
    public function update(
        Request $request,
        Company $company,
        Subscribe $subscribe,
        SwapPlan $swapPlan,
    ): RedirectResponse {
        abort_if($request->user('admin')?->cannot('platform.tenants.manage') ?? true, 403);

        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'slug')->whereNull('deleted_at')],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
        ]);

        $plan = Plan::query()->where('slug', $validated['plan'])->firstOrFail();
        $interval = BillingInterval::from($validated['interval']);
        $current = $this->liveSubscription($company);

        try {
            $this->tenant->scopeTo($company, function () use ($company, $plan, $interval, $current, $subscribe, $swapPlan): void {
                $current instanceof Subscription
                    ? $swapPlan->handle($current, $plan, $interval)
                    : $subscribe->handle($company, $plan, $interval, null, null, self::GATEWAY);
            });
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->security->log(
            SecurityEvent::TenantPlanChanged,
            $request->user('admin'),
            __('Set :company to the :plan plan.', ['company' => $company->name, 'plan' => $plan->name]),
            [
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'interval' => $interval->value,
                'from_plan_id' => $current?->plan_id,
            ],
        );

        return back()->with('success', __('Workspace moved to the :plan plan.', ['plan' => $plan->name]));
    }

    /**
     * Push the trial end date out, for a workspace still evaluating.
     */
    public function extendTrial(Request $request, Company $company): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.manage') ?? true, 403);

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $days = (int) $validated['days'];
        $subscription = $this->liveSubscription($company);

        // Extend from whichever end date is still in the future, so granting
        // 14 days to a workspace with 3 left gives 17, not 14.
        $extendFrom = static fn (?CarbonImmutable $current): CarbonImmutable => $current !== null && $current->isFuture()
            ? $current
            : CarbonImmutable::now();

        if ($subscription instanceof Subscription) {
            $subscription->forceFill([
                'trial_ends_at' => $extendFrom($subscription->trial_ends_at)->addDays($days),
            ])->save();
        }

        // The workspace's own trial window is what gates a tenant that never
        // subscribed at all, so it moves too.
        $company->forceFill([
            'trial_ends_at' => $extendFrom($company->trial_ends_at)->addDays($days),
        ])->save();

        $this->security->log(
            SecurityEvent::TenantTrialExtended,
            $request->user('admin'),
            __('Extended :company\'s trial by :days days.', ['company' => $company->name, 'days' => $days]),
            ['company_id' => $company->id, 'days' => $days],
        );

        return back()->with('success', __('Trial extended by :days days.', ['days' => $days]));
    }

    public function destroy(Request $request, Company $company, CancelSubscription $cancel): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.manage') ?? true, 403);

        $subscription = $this->liveSubscription($company);

        if (! $subscription instanceof Subscription) {
            return back()->with('error', __('This workspace has no running subscription.'));
        }

        try {
            $this->tenant->scopeTo($company, static function () use ($cancel, $subscription, $request): void {
                $cancel->handle($subscription, $request->boolean('immediately'));
            });
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->security->log(
            SecurityEvent::TenantSubscriptionCancelled,
            $request->user('admin'),
            __('Cancelled :company\'s subscription.', ['company' => $company->name]),
            ['company_id' => $company->id, 'subscription_id' => $subscription->id],
        );

        return back()->with('success', __('Subscription cancelled.'));
    }

    protected function liveSubscription(Company $company): ?Subscription
    {
        return $this->tenant->scopeTo(
            $company,
            static fn (): ?Subscription => $company->activeSubscription()->with('plan')->first(),
        );
    }
}
