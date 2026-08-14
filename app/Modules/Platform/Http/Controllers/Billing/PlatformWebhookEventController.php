<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Modules\Billing\DTOs\GatewayWebhookData;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Billing\Services\WebhookProcessor;
use App\Modules\Platform\Http\Controllers\Billing\Concerns\ManagesPlatformBilling;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * What the payment processor has told us, and whether we listened.
 *
 * The signal here is the unprocessed row: an event that arrived, was stored,
 * and never landed. Without this screen a silently failing integration is
 * discovered from a customer email weeks later. Replay re-runs the stored
 * payload through {@see WebhookProcessor} — the same path the live endpoint
 * takes — and is safe to repeat, because the handlers are written to no-op on
 * a record that is already in the target state.
 */
class PlatformWebhookEventController extends Controller
{
    use ManagesPlatformBilling;

    public function __construct(protected WebhookProcessor $processor) {}

    public function index(Request $request): Response
    {
        $this->authorizeBillingRead($request);

        $query = WebhookEvent::query();

        $table = TableBuilder::for($query, $request, 'events')
            ->columns([
                Column::make('type', __('Event'))->sortable()->searchable()->locked(),
                Column::make('gateway', __('Gateway'))->sortable(),
                Column::make('event_id', __('Event ID'))->searchable()->hidden(),
                Column::make('processed_at', __('Processed'))->sortable(),
                Column::make('created_at', __('Received'))->sortable(),
            ])
            ->filters([
                Filter::make('gateway', __('Gateway'))->options($this->gatewayOptions()),
                Filter::make('created_at', __('Received'))->dateRange(),
            ])
            ->defaultSort('created_at', 'desc')
            ->transform(fn (WebhookEvent $event): array => [
                'id' => $event->id,
                'gateway' => $event->gateway,
                'event_id' => $event->event_id,
                'type' => $event->type,
                'processed' => $event->processed_at !== null,
                'processed_at' => $event->processed_at?->toIso8601String(),
                'created_at' => $event->created_at?->toIso8601String(),
                'payload' => $event->payload,
            ]);

        return Inertia::render('admin/billing/events', [
            'table' => $table->toArray(),
            'unprocessed' => WebhookEvent::query()->whereNull('processed_at')->count(),
            'can' => [
                'replay' => $request->user('admin')?->can('platform.billing.manage') ?? false,
            ],
        ]);
    }

    public function replay(Request $request, WebhookEvent $event): RedirectResponse
    {
        $this->authorizeBillingWrite($request);

        $payload = $event->payload;

        if (! is_array($payload)) {
            return back()->with('error', __('This event has no stored payload to replay.'));
        }

        try {
            $this->processor->apply(new GatewayWebhookData(
                gateway: $event->gateway,
                eventId: $event->event_id,
                type: $event->type,
                payload: $payload,
            ));
        } catch (Throwable $exception) {
            // The failure is the answer the operator came for, so it is shown
            // rather than swallowed — and the event stays unprocessed.
            return back()->with('error', __('Replay failed: :message', ['message' => $exception->getMessage()]));
        }

        $event->update(['processed_at' => CarbonImmutable::now()]);

        return back()->with('success', __('Event :type replayed.', ['type' => $event->type]));
    }

    /**
     * @return array<array-key, string>
     */
    protected function gatewayOptions(): array
    {
        $options = [];

        foreach (WebhookEvent::query()->distinct()->orderBy('gateway')->pluck('gateway') as $gateway) {
            $options[(string) $gateway] = (string) $gateway;
        }

        return $options;
    }
}
