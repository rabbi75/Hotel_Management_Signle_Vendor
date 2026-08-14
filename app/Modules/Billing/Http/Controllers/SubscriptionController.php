<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\CancelSubscription;
use App\Modules\Billing\Actions\ResumeSubscription;
use App\Modules\Billing\Actions\StartCheckout;
use App\Modules\Billing\Actions\SwapPlan;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Http\Requests\CancelSubscriptionRequest;
use App\Modules\Billing\Http\Requests\SubscribeRequest;
use App\Modules\Billing\Http\Requests\SwapPlanRequest;
use App\Modules\Billing\Http\Resources\InvoiceResource;
use App\Modules\Billing\Http\Resources\PaymentMethodResource;
use App\Modules\Billing\Http\Resources\PlanResource;
use App\Modules\Billing\Http\Resources\SubscriptionResource;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\PaymentMethod;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionLimits $limits,
        protected GatewayManager $gateways,
    ) {}

    /**
     * The billing home: what the workspace is on, what it is using, what is next.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Subscription::class);

        $company = $this->company($request);
        $subscription = $this->limits->subscription($company);

        return Inertia::render('billing/index', [
            'subscription' => $subscription instanceof Subscription
                ? (new SubscriptionResource($subscription))->resolve($request)
                : null,
            'meters' => $this->limits->meters($company),
            'invoices' => InvoiceResource::collection(
                Invoice::query()->latest('issued_at')->limit(5)->get(),
            )->resolve($request),
            'next_invoice' => $this->nextInvoice($subscription),
            'payment_method' => $this->defaultPaymentMethod($request),
            'gateway' => $this->gateways->defaultDriver(),
            'billing_enabled' => (bool) config('saas.billing.enabled'),
            'can' => [
                'subscribe' => Gate::allows('create', Subscription::class),
                'cancel' => $subscription instanceof Subscription && Gate::allows('cancel', $subscription),
                'manage_payment_methods' => Gate::allows('viewAny', PaymentMethod::class),
                'download_invoices' => $request->user()?->can('billing.invoices.download') ?? false,
            ],
        ]);
    }

    /**
     * The plan picker, with a proration preview for the current subscription.
     */
    public function plans(Request $request): Response
    {
        Gate::authorize('viewAny', Subscription::class);

        $company = $this->company($request);
        $subscription = $this->limits->subscription($company);
        $plans = Plan::query()->active()->where('is_public', true)->orderBy('sort')->orderBy('id')->get();

        return Inertia::render('billing/plans', [
            'plans' => PlanResource::collection($plans)->resolve($request),
            'subscription' => $subscription instanceof Subscription
                ? (new SubscriptionResource($subscription))->resolve($request)
                : null,
            'previews' => $subscription instanceof Subscription
                ? $this->previews($subscription, $plans->all())
                : [],

            // Which processors can take money for each plan, keyed by slug. A
            // plan priced in a currency no enabled gateway settles gets an
            // empty list, and the picker says so rather than offering a button
            // that would fail at the processor.
            'gateways' => $this->gatewayOptions($plans->all()),

            'can' => [
                'subscribe' => Gate::allows('create', Subscription::class),
            ],
        ]);
    }

    /**
     * Send the customer to the processor's checkout.
     *
     * Nothing is recorded at this point — see {@see StartCheckout}. The
     * subscription is created on the return leg, once the payment has been
     * confirmed with the processor.
     */
    public function store(SubscribeRequest $request, StartCheckout $startCheckout): SymfonyResponse
    {
        $company = $this->company($request);
        $plan = Plan::query()->where('slug', $request->string('plan')->toString())->firstOrFail();

        try {
            $session = $startCheckout->handle(
                $company,
                $plan,
                $request->billingInterval(),
                $request->string('gateway')->toString() ?: null,
                $request->couponCode(),
            );
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        // Away to the processor. `away()` rather than `to()` because the target
        // is off-site and Inertia would otherwise try to fetch it as a page.
        return Inertia::location($session->url);
    }

    public function update(SwapPlanRequest $request, Subscription $subscription, SwapPlan $swapPlan): RedirectResponse
    {
        $plan = Plan::query()->where('slug', $request->string('plan')->toString())->firstOrFail();

        try {
            $swapPlan->handle($subscription->load('plan'), $plan, $request->billingInterval());
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('billing.index')
            ->with('success', __('Your plan is now :plan.', ['plan' => $plan->name]));
    }

    public function destroy(
        CancelSubscriptionRequest $request,
        Subscription $subscription,
        CancelSubscription $cancel,
    ): RedirectResponse {
        try {
            $cancel->handle($subscription->load('plan'), $request->boolean('immediately'));
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('billing.index')
            ->with('success', __('Your subscription has been cancelled.'));
    }

    public function resume(Subscription $subscription, ResumeSubscription $resume): RedirectResponse
    {
        Gate::authorize('resume', $subscription);

        try {
            $resume->handle($subscription->load('plan'));
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Your subscription has been resumed.'));
    }

    /**
     * @param  list<Plan>  $plans
     * @return array<string, list<array{driver: string, label: string, test_mode: bool}>>
     */
    protected function gatewayOptions(array $plans): array
    {
        $options = [];

        foreach ($plans as $plan) {
            $options[$plan->slug] = $this->gateways->enabledFor($plan)
                ->map(static fn (PaymentGatewayConfig $config): array => [
                    'driver' => $config->driver,
                    'label' => $config->label,
                    // Surfaced so nobody mistakes a sandbox for a live charge.
                    'test_mode' => $config->is_test_mode,
                ])
                ->values()
                ->all();
        }

        return $options;
    }

    protected function company(Request $request): Company
    {
        $company = current_company();

        abort_if(! $company instanceof Company, 403, __('No active workspace.'));

        return $company;
    }

    protected function coupon(?string $code): ?Coupon
    {
        if ($code === null) {
            return null;
        }

        return Coupon::query()->where('code', $code)->first();
    }

    /**
     * @param  list<Plan>  $plans
     * @return array<string, array{credit: int, charge: int, due: int, due_formatted: string, prorated: bool}>
     */
    protected function previews(Subscription $subscription, array $plans): array
    {
        $swap = app(SwapPlan::class);
        $previews = [];

        foreach ($plans as $plan) {
            foreach (BillingInterval::cases() as $interval) {
                $preview = $swap->preview($subscription->loadMissing('plan'), $plan, $interval);

                $previews["{$plan->slug}:{$interval->value}"] = [
                    'credit' => $preview['credit']->amount,
                    'charge' => $preview['charge']->amount,
                    'due' => $preview['due']->amount,
                    'due_formatted' => $preview['due']->format(),
                    'prorated' => $preview['prorated'],
                ];
            }
        }

        return $previews;
    }

    /**
     * @return array{amount: int, amount_formatted: string, date: string|null}|null
     */
    protected function nextInvoice(?Subscription $subscription): ?array
    {
        if (! $subscription instanceof Subscription || ! $subscription->status->isLive() || $subscription->isCancelling()) {
            return null;
        }

        $amount = $subscription->price();

        return [
            'amount' => $amount->amount,
            'amount_formatted' => $amount->format(),
            'date' => $subscription->current_period_end?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function defaultPaymentMethod(Request $request): ?array
    {
        $method = PaymentMethod::query()->where('is_default', true)->first();

        return $method instanceof PaymentMethod
            ? (new PaymentMethodResource($method))->resolve($request)
            : null;
    }
}
