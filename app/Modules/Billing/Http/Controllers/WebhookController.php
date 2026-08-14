<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\DTOs\GatewayWebhookData;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Billing\Services\WebhookProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound gateway notifications.
 *
 * Declared on the API route group, which carries no session and therefore no
 * CSRF token check — a processor cannot present one. Authenticity comes from
 * the driver's signature verification instead, and replay protection from the
 * unique (gateway, event_id) index.
 *
 * The mapping itself lives in {@see WebhookProcessor} so the console can replay
 * a stored event through exactly the same code.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected GatewayManager $gateways,
        protected WebhookProcessor $processor,
    ) {}

    public function __invoke(Request $request, ?string $gateway = null): JsonResponse
    {
        $driver = $this->gateways->driver($gateway);
        $event = $driver->handleWebhook($request);

        if (! $event instanceof GatewayWebhookData) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        try {
            $record = WebhookEvent::query()->create([
                'gateway' => $event->gateway,
                'event_id' => $event->eventId,
                'type' => $event->type,
                'payload' => $event->payload,
                'processed_at' => null,
            ]);
        } catch (QueryException) {
            // Already seen. Acknowledge so the processor stops redelivering.
            return response()->json(['message' => 'Already processed.']);
        }

        $this->processor->apply($event);

        $record->update(['processed_at' => CarbonImmutable::now()]);

        return response()->json(['message' => 'Processed.']);
    }
}
