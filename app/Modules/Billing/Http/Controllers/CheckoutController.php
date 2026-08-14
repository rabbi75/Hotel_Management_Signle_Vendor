<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\Subscribe;
use App\Modules\Billing\DTOs\CheckoutResult;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\TransactionStatus;
use App\Modules\Billing\Enums\TransactionType;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The leg a customer comes back on after paying.
 *
 * Two independent things have to be true before a subscription is granted, and
 * neither is sufficient alone:
 *
 *   1. The URL carries a valid signature — the parameters are ones this
 *      application issued, not ones the customer typed.
 *   2. The driver confirms the payment with the processor directly.
 *
 * Without the first, anybody could name a plan; without the second, anybody
 * could claim to have paid for it.
 */
class CheckoutController extends Controller
{
    /**
     * Query parameters a processor may append to the return URL it was handed.
     *
     * Excluded from the signature check, because a signature computed over the
     * whole query string would reject a URL the processor legitimately added to.
     * None of these decides what is granted — each is only ever fed back into a
     * server-side verification call, so a forged one buys nothing.
     *
     * `ref` is the kit's own: the POST-callback bridge appends it for the
     * processors that return by POST.
     *
     * @var list<string>
     */
    public const CALLBACK_PARAMETERS = [
        'ref',
        'reference',
        'trxref',
        'paymentID',
        'payment_ref_id',
        'order_id',
        'status',
    ];

    public function __construct(protected GatewayManager $gateways) {}

    public function return(Request $request, Subscribe $subscribe): RedirectResponse
    {
        $company = current_company();
        $plan = Plan::query()->where('slug', $request->string('plan')->toString())->first();
        $driver = $request->string('gateway')->toString();

        if (! $company instanceof Company || ! $plan instanceof Plan) {
            return redirect()->route('billing.plans')->with('error', __('That checkout is no longer valid.'));
        }

        // The signed URL names the workspace it was issued for. A session that
        // has since switched workspaces must not spend this checkout on a
        // different one.
        if ($request->string('company')->toString() !== $company->uuid) {
            return redirect()->route('billing.plans')->with('error', __('That checkout belongs to another workspace.'));
        }

        if ($this->liveSubscription($company) instanceof Subscription) {
            return redirect()->route('billing.index')->with('error', __('This workspace already has an active subscription.'));
        }

        $result = $this->gateways->driver($driver)->verifyCheckout($request);

        if (! $result instanceof CheckoutResult) {
            // Unverifiable is not the same as unpaid: the customer may well have
            // paid and the webhook may still land, so this says so rather than
            // asserting failure.
            return redirect()->route('billing.index')
                ->with('warning', __('We could not confirm that payment yet. It will appear here once the processor reports it.'));
        }

        if (! $result->paid && $driver !== 'manual') {
            return redirect()->route('billing.plans')
                ->with('error', __('That payment did not go through. Nothing has been charged.'));
        }

        $interval = BillingInterval::tryFrom($request->string('interval')->toString()) ?? BillingInterval::Monthly;
        $coupon = $this->coupon($request->string('coupon')->toString());

        try {
            $subscription = $subscribe->handle($company, $plan, $interval, $coupon, null, $driver);
        } catch (BillingException $exception) {
            return redirect()->route('billing.plans')->with('error', $exception->getMessage());
        }

        if ($result->paid) {
            $this->recordPayment($subscription, $result);
        }

        return redirect()->route('billing.index')->with(
            'success',
            $result->paid
                ? __('You are now subscribed to :plan.', ['plan' => $plan->name])
                : __('Your :plan subscription is set up. The invoice is due once payment is received.', ['plan' => $plan->name]),
        );
    }

    /**
     * Braintree renders its own card form in our page rather than hosting one,
     * so this is where that customer lands. The signature is what makes the
     * parameters trustworthy without a session of its own.
     */
    public function braintree(Request $request): Response
    {
        $plan = Plan::query()->where('slug', $request->string('plan')->toString())->firstOrFail();
        $interval = BillingInterval::tryFrom($request->string('interval')->toString()) ?? BillingInterval::Monthly;

        return Inertia::render('billing/checkout-braintree', [
            'plan' => ['name' => $plan->name, 'price' => $plan->priceFor($interval)->format()],
            'amount' => $plan->priceFor($interval)->amount,
            'currency' => $plan->currency,
            'clientToken' => $this->gateways->driver('braintree')->checkout(
                current_company() ?? new Company,
                $plan,
                $interval,
                (string) $request->query('return', ''),
                (string) $request->query('cancel', ''),
            )->reference,
            'returnUrl' => (string) $request->query('return', ''),
            'cancelUrl' => (string) $request->query('cancel', ''),
        ]);
    }

    /**
     * Mark the invoice the subscription just raised as paid, and write the
     * charge.
     *
     * Deliberately the same shape SettleInvoice produces, so refunds and the
     * revenue figures read a hosted payment exactly as they read an offline one.
     */
    protected function recordPayment(Subscription $subscription, CheckoutResult $result): void
    {
        DB::transaction(function () use ($subscription, $result): void {
            $invoice = Invoice::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->where('subscription_id', $subscription->id)
                ->latest('id')
                ->first();

            $now = CarbonImmutable::now();

            $transaction = new Transaction([
                'invoice_id' => $invoice?->id,
                'gateway' => $result->gateway,
                // The charge id, not the checkout reference: it is what a later
                // refund is issued against.
                'gateway_id' => $result->transactionId ?? $result->reference,
                'type' => TransactionType::Charge,
                'status' => TransactionStatus::Succeeded,
                'amount' => $result->amount,
                'currency' => $result->currency,
                'processed_at' => $now,
                'meta' => ['checkout_reference' => $result->reference],
            ]);
            $transaction->company_id = $subscription->company_id;
            $transaction->save();

            if ($invoice instanceof Invoice && ! $invoice->status->isSettled()) {
                $invoice->status = InvoiceStatus::Paid;
                $invoice->paid_at = $now;
                $invoice->gateway = $result->gateway;
                $invoice->gateway_id = $result->reference;
                $invoice->save();
            }
        });
    }

    protected function coupon(string $code): ?Coupon
    {
        return $code === '' ? null : Coupon::query()->where('code', $code)->first();
    }

    protected function liveSubscription(Company $company): ?Subscription
    {
        return Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->live()
            ->first();
    }
}
