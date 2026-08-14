<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\GatewayWebhookData;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\InvoicePaidNotification;
use App\Modules\Billing\Notifications\PaymentFailedNotification;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;

/**
 * Maps a normalised gateway event onto the kit's own records.
 *
 * Extracted from the webhook endpoint so the console can replay a stored event
 * through exactly the same code. A replay that took a different path would
 * prove nothing about why the original failed.
 *
 * Every lookup lifts the tenant scope and locates records by their globally
 * unique gateway id: a webhook — live or replayed — carries no session and
 * therefore no resolved workspace.
 */
class WebhookProcessor
{
    public function apply(GatewayWebhookData $event): void
    {
        $object = data_get($event->payload, 'data.object');
        $object = is_array($object) ? $object : [];

        match (true) {
            str_starts_with($event->type, 'customer.subscription.') => $this->syncSubscription($event, $object),
            $event->type === 'invoice.payment_succeeded' || $event->type === 'invoice.paid' => $this->markInvoicePaid($object),
            $event->type === 'invoice.payment_failed' => $this->markPaymentFailed($object),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function syncSubscription(GatewayWebhookData $event, array $object): void
    {
        $subscription = $this->findSubscription($event->gateway, $object);

        if (! $subscription instanceof Subscription) {
            return;
        }

        $status = match (data_get($object, 'status')) {
            'trialing' => SubscriptionStatus::Trialing,
            'active' => SubscriptionStatus::Active,
            'past_due', 'unpaid' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Canceled,
            'incomplete', 'incomplete_expired' => SubscriptionStatus::Incomplete,
            default => $subscription->status,
        };

        $subscription->status = $status;

        if ($status === SubscriptionStatus::Canceled && $subscription->ended_at === null) {
            $subscription->ended_at = CarbonImmutable::now();
        }

        $subscription->save();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function markInvoicePaid(array $object): void
    {
        $invoice = $this->findInvoice($object);

        if (! $invoice instanceof Invoice || $invoice->isPaid()) {
            return;
        }

        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_at = CarbonImmutable::now();
        $invoice->save();

        $invoice->company->owner->notify(new InvoicePaidNotification($invoice));
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function markPaymentFailed(array $object): void
    {
        $invoice = $this->findInvoice($object);

        if (! $invoice instanceof Invoice) {
            return;
        }

        $subscription = $invoice->subscription;

        if (! $subscription instanceof Subscription) {
            return;
        }

        $subscription->status = SubscriptionStatus::PastDue;
        $subscription->past_due_since ??= CarbonImmutable::now();
        $subscription->dunning_attempts++;
        $subscription->save();

        $invoice->company->owner->notify(new PaymentFailedNotification(
            $subscription->load('plan'),
            $subscription->dunning_attempts,
            (int) config('saas.billing.grace_days', 3),
        ));
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function findSubscription(string $gateway, array $object): ?Subscription
    {
        $id = data_get($object, 'id');

        if (! is_string($id)) {
            return null;
        }

        return Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('gateway', $gateway)
            ->where('gateway_id', $id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function findInvoice(array $object): ?Invoice
    {
        $id = data_get($object, 'id');
        $number = data_get($object, 'number');

        $query = Invoice::query()->withoutGlobalScope(CompanyScope::class);

        if (is_string($id)) {
            $match = (clone $query)->where('gateway_id', $id)->first();

            if ($match instanceof Invoice) {
                return $match;
            }
        }

        return is_string($number) ? $query->where('number', $number)->first() : null;
    }
}
