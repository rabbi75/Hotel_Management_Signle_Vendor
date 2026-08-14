<?php

declare(strict_types=1);

namespace App\Modules\Api\Services;

use App\Modules\Api\Enums\WebhookDeliveryStatus;
use App\Modules\Api\Jobs\DeliverWebhook;
use App\Modules\Api\Models\WebhookDelivery;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Support\WebhookEventRegistry;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Facades\Log;

/**
 * Fans an application event out to every subscribed endpoint in a workspace.
 */
class WebhookDispatcher
{
    public function __construct(protected WebhookEventRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return list<WebhookDelivery>
     */
    public function dispatch(string $event, array $payload, ?int $companyId = null): array
    {
        $companyId ??= current_company_id();

        if ($companyId === null) {
            return [];
        }

        if (! $this->registry->has($event)) {
            Log::warning('Refused to dispatch an unregistered webhook event.', ['event' => $event]);

            return [];
        }

        $deliveries = [];

        foreach ($this->endpointsFor($event, $companyId) as $endpoint) {
            $deliveries[] = $this->queue($endpoint, $event, $payload);
        }

        return $deliveries;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queue(WebhookEndpoint $endpoint, string $event, array $payload): WebhookDelivery
    {
        $delivery = new WebhookDelivery([
            'company_id' => $endpoint->company_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $event,
            'payload' => $payload,
            'attempt' => 0,
            'status' => WebhookDeliveryStatus::Pending,
            'status_code' => null,
            'response_body' => null,
            'error' => null,
            'duration_ms' => null,
            'delivered_at' => null,
            'next_retry_at' => null,
        ]);

        $delivery->save();

        DeliverWebhook::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * Re-send an existing delivery as a fresh attempt, from the UI.
     */
    public function redeliver(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Pending,
            'next_retry_at' => null,
            'error' => null,
        ])->save();

        DeliverWebhook::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * @return list<WebhookEndpoint>
     */
    protected function endpointsFor(string $event, int $companyId): array
    {
        return WebhookEndpoint::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('disabled_at')
            ->get()
            ->filter(static fn (WebhookEndpoint $endpoint): bool => $endpoint->subscribesTo($event))
            ->values()
            ->all();
    }
}
