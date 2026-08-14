<?php

declare(strict_types=1);

namespace App\Modules\Api\Jobs;

use App\Modules\Api\Enums\WebhookDeliveryStatus;
use App\Modules\Api\Models\WebhookDelivery;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Support\WebhookSignature;
use App\Modules\Api\Support\WebhookUrlValidator;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

/**
 * Posts one webhook delivery and records the outcome.
 *
 * Retries are scheduled by re-dispatching with a delay taken from
 * `saas.api.webhooks.retry_backoff` rather than by letting the queue worker
 * retry: the schedule has to be visible in `next_retry_at` so an operator can
 * see when the next attempt is due.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->find($this->deliveryId);

        if (! $delivery instanceof WebhookDelivery) {
            return;
        }

        $endpoint = WebhookEndpoint::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->find($delivery->webhook_endpoint_id);

        if (! $endpoint instanceof WebhookEndpoint || ! $endpoint->isDeliverable()) {
            $this->fail($delivery, $endpoint, __('The endpoint is disabled.'), null, null);

            return;
        }

        $attempt = $delivery->attempt + 1;
        $timestamp = CarbonImmutable::now()->getTimestamp();
        $body = $this->body($delivery, $timestamp);
        $startedAt = microtime(true);

        try {
            if (($reason = WebhookUrlValidator::reject($endpoint->url)) !== null) {
                throw new RuntimeException($reason);
            }

            $response = Http::withHeaders([
                (string) config('saas.api.webhooks.signature_header') => WebhookSignature::sign($body, $endpoint->secret, $timestamp),
                WebhookSignature::TIMESTAMP_HEADER => (string) $timestamp,
                'Content-Type' => 'application/json',
                'User-Agent' => config('saas.brand.short_name').'-Webhooks/1',
                'X-Webhook-Event' => $delivery->event,
                'X-Webhook-Delivery' => (string) $delivery->id,
            ])
                ->timeout((int) config('saas.api.webhooks.timeout'))
                ->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true, 'on_redirect' => $this->redirectGuard()]])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->successful()) {
                $this->succeed($delivery, $endpoint, $attempt, $response->status(), $response->body(), $duration);

                return;
            }

            $this->fail($delivery, $endpoint, __('Endpoint responded with :status.', ['status' => $response->status()]), $response->status(), $response->body(), $attempt, $duration);
        } catch (Throwable $exception) {
            $this->fail($delivery, $endpoint, $exception->getMessage(), null, null, $attempt, (int) round((microtime(true) - $startedAt) * 1000));
        }
    }

    /**
     * Guzzle follows redirects itself, so the destination is re-validated at
     * each hop — otherwise an endpoint could pass validation and then 302 to
     * the cloud metadata service.
     */
    protected function redirectGuard(): callable
    {
        return static function (RequestInterface $request, mixed $response, mixed $uri): void {
            $host = is_object($uri) && method_exists($uri, 'getHost') ? (string) $uri->getHost() : '';
            $scheme = is_object($uri) && method_exists($uri, 'getScheme') ? (string) $uri->getScheme() : '';

            if (mb_strtolower($scheme) !== 'https' || WebhookUrlValidator::rejectHost($host) !== null) {
                throw new RuntimeException('Webhook redirect target rejected: '.$host);
            }
        };
    }

    protected function body(WebhookDelivery $delivery, int $timestamp): string
    {
        return json_encode([
            'id' => $delivery->id,
            'event' => $delivery->event,
            'created_at' => CarbonImmutable::createFromTimestamp($timestamp)->toIso8601String(),
            'data' => $delivery->payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    protected function succeed(WebhookDelivery $delivery, WebhookEndpoint $endpoint, int $attempt, int $status, string $body, int $duration): void
    {
        $delivery->forceFill([
            'attempt' => $attempt,
            'status' => WebhookDeliveryStatus::Delivered,
            'status_code' => $status,
            'response_body' => mb_substr($body, 0, 4000),
            'error' => null,
            'duration_ms' => $duration,
            'delivered_at' => CarbonImmutable::now(),
            'next_retry_at' => null,
        ])->save();

        $endpoint->forceFill(['failure_count' => 0, 'last_success_at' => CarbonImmutable::now()])->save();
    }

    protected function fail(
        WebhookDelivery $delivery,
        ?WebhookEndpoint $endpoint,
        string $error,
        ?int $status,
        ?string $body,
        int $attempt = 0,
        int $duration = 0,
    ): void {
        /** @var list<int> $backoff */
        $backoff = config('saas.api.webhooks.retry_backoff');
        $maxAttempts = (int) config('saas.api.webhooks.max_attempts');
        $attempt = max($attempt, $delivery->attempt);

        $willRetry = $endpoint instanceof WebhookEndpoint && $attempt > 0 && $attempt < $maxAttempts;
        $delay = $backoff[min($attempt - 1, count($backoff) - 1)] ?? null;

        $delivery->forceFill([
            'attempt' => $attempt,
            'status' => $willRetry ? WebhookDeliveryStatus::Retrying : WebhookDeliveryStatus::Failed,
            'status_code' => $status,
            'response_body' => $body === null ? null : mb_substr($body, 0, 4000),
            'error' => mb_substr($error, 0, 2000),
            'duration_ms' => $duration,
            'next_retry_at' => $willRetry && $delay !== null ? CarbonImmutable::now()->addSeconds($delay) : null,
        ])->save();

        if (! $endpoint instanceof WebhookEndpoint) {
            return;
        }

        $failures = $endpoint->failure_count + 1;

        $endpoint->forceFill([
            'failure_count' => $failures,
            'last_failure_at' => CarbonImmutable::now(),
            'disabled_at' => $failures >= WebhookEndpoint::FAILURE_THRESHOLD ? CarbonImmutable::now() : $endpoint->disabled_at,
            'is_active' => $failures < WebhookEndpoint::FAILURE_THRESHOLD && $endpoint->is_active,
        ])->save();

        if ($willRetry && $delay !== null) {
            self::dispatch($delivery->id)->delay($delay);
        }
    }
}
