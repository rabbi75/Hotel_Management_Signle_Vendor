<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Http\Requests\StoreWebhookEndpointRequest;
use App\Modules\Api\Http\Requests\UpdateWebhookEndpointRequest;
use App\Modules\Api\Http\Resources\WebhookDeliveryResource;
use App\Modules\Api\Http\Resources\WebhookEndpointResource;
use App\Modules\Api\Models\WebhookDelivery;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Services\WebhookDispatcher;
use App\Modules\Api\Support\WebhookEventRegistry;
use App\Modules\Api\Support\WebhookSignature;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function __construct(
        protected WebhookEventRegistry $registry,
        protected WebhookDispatcher $dispatcher,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', WebhookEndpoint::class);

        $deliveries = TableBuilder::for(
            WebhookDelivery::query()->with('endpoint'),
            $request,
            'deliveries',
        )
            ->columns([
                Column::make('event', __('Event'))->sortable()->searchable()->locked(),
                Column::make('endpoint_url', __('Endpoint')),
                Column::make('status', __('Status'))->sortable(),
                Column::make('status_code', __('Code'))->align('right'),
                Column::make('attempt', __('Attempt'))->align('right'),
                Column::make('created_at', __('Queued'))->sortable(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->options(['Pending' => 'pending', 'Delivered' => 'delivered', 'Retrying' => 'retrying', 'Failed' => 'failed']),
                Filter::make('webhook_endpoint_id', __('Endpoint'))->options($this->endpointOptions()),
            ])
            ->defaultSort('created_at')
            ->transform(fn (WebhookDelivery $delivery): array => (new WebhookDeliveryResource($delivery))->resolve($request));

        return Inertia::render('api/webhooks/index', [
            'endpoints' => WebhookEndpointResource::collection(
                WebhookEndpoint::query()->orderByDesc('id')->get(),
            )->resolve($request),
            'deliveries' => $deliveries->toArray(),
            'events' => $this->registry->all(),
            'signature_header' => (string) config('saas.api.webhooks.signature_header'),
            'timestamp_header' => WebhookSignature::TIMESTAMP_HEADER,

            // Present on exactly one render — the redirect that follows
            // creation. The secret is not retrievable afterwards.
            'created_secret' => $request->session()->get('created_webhook_secret'),
            'can' => ['manage' => Gate::allows('create', WebhookEndpoint::class)],
        ]);
    }

    public function store(StoreWebhookEndpointRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<string> $events */
        $events = array_values(array_map(strval(...), (array) $request->input('events', [])));

        $endpoint = new WebhookEndpoint([
            'company_id' => current_company_id(),
            'created_by' => $user->id,
            'url' => $request->string('url')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'events' => $events,
            'secret' => WebhookSignature::generateSecret(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $endpoint->save();

        $this->security->log(SecurityEvent::WebhookEndpointCreated, $user, __('Webhook endpoint created.'), [
            'endpoint_id' => $endpoint->id,
            'url' => $endpoint->url,
            'events' => $events,
        ]);

        // The signing secret is shown once, on creation, and never again.
        return back()->with('created_webhook_secret', [
            'endpoint_id' => $endpoint->id,
            'secret' => $endpoint->secret,
        ]);
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $attributes = [];

        if ($request->has('url')) {
            $attributes['url'] = $request->string('url')->toString();
        }

        if ($request->has('description')) {
            $attributes['description'] = $request->string('description')->toString() ?: null;
        }

        if ($request->has('events')) {
            $attributes['events'] = array_values(array_map(strval(...), (array) $request->input('events', [])));
        }

        if ($request->has('is_active')) {
            $attributes['is_active'] = $request->boolean('is_active');

            // Re-enabling clears the auto-disable state, otherwise the endpoint
            // would stay undeliverable while appearing active.
            if ($attributes['is_active'] === true) {
                $endpoint->forceFill(['disabled_at' => null, 'failure_count' => 0]);
            }
        }

        $endpoint->fill($attributes)->save();

        return back()->with('success', __('Webhook endpoint updated.'));
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        Gate::authorize('delete', $endpoint);

        $user = $request->user();

        $this->security->log(SecurityEvent::WebhookEndpointDeleted, $user instanceof User ? $user : null, __('Webhook endpoint deleted.'), [
            'endpoint_id' => $endpoint->id,
            'url' => $endpoint->url,
        ]);

        $endpoint->delete();

        return back()->with('success', __('Webhook endpoint deleted.'));
    }

    public function redeliver(WebhookDelivery $delivery): RedirectResponse
    {
        $endpoint = $delivery->endpoint;

        Gate::authorize('update', $endpoint ?? WebhookEndpoint::class);

        $this->dispatcher->redeliver($delivery);

        return back()->with('success', __('Delivery queued for retry.'));
    }

    /**
     * @return array<string, string>
     */
    protected function endpointOptions(): array
    {
        return WebhookEndpoint::query()
            ->orderBy('url')
            ->pluck('url', 'id')
            ->mapWithKeys(static fn (string $url, int|string $id): array => [$url => (string) $id])
            ->all();
    }
}
