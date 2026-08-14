<?php

declare(strict_types=1);

use App\Modules\Api\Enums\WebhookDeliveryStatus;
use App\Modules\Api\Jobs\DeliverWebhook;
use App\Modules\Api\Models\WebhookDelivery;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Services\WebhookDispatcher;
use App\Modules\Api\Support\WebhookSignature;
use App\Modules\Api\Support\WebhookUrlValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    // Host resolution is stubbed so the suite never depends on live DNS; the
    // address returned is a public one, which is what the guard must accept.
    WebhookUrlValidator::resolveUsing(static fn (string $host): array => ['93.184.216.34']);
});

afterEach(function (): void {
    WebhookUrlValidator::resolveUsing(null);
});

it('redirects an unauthenticated visitor', function (): void {
    $this->get(route('api.webhooks.index'))->assertRedirect(route('login'));
});

it('forbids a member without the webhook permission', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->get(route('api.webhooks.index'), inertiaHeaders())
        ->assertForbidden();
});

it('creates an endpoint and reveals the signing secret once', function (): void {
    $company = workspace();
    $user = memberWith(['api.webhooks.manage'], $company)->refresh();

    $response = actingAsMember($user, $company)->post(route('api.webhooks.store'), [
        'url' => 'https://hooks.example.com/inbound',
        'events' => ['user.created'],
    ]);

    $response->assertRedirect()->assertSessionHas('created_webhook_secret');

    $endpoint = WebhookEndpoint::query()->where('company_id', $company->id)->firstOrFail();

    // The listing payload carries a placeholder, never the secret itself.
    $listing = actingAsMember($user, $company)
        ->get(route('api.webhooks.index'), inertiaHeaders())
        ->assertOk();

    expect(json_encode($listing->json('props.endpoints')))->not->toContain($endpoint->secret);
});

it('rejects a webhook URL that resolves to a loopback address', function (): void {
    $company = workspace();
    $user = memberWith(['api.webhooks.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('api.webhooks.store'), ['url' => 'https://127.0.0.1/hook', 'events' => ['user.created']])
        ->assertSessionHasErrors('url');
});

it('rejects private, link-local and metadata addresses', function (string $url): void {
    expect(WebhookUrlValidator::reject($url))->not->toBeNull();
})->with([
    'http scheme' => 'http://hooks.example.com/inbound',
    'loopback' => 'https://127.0.0.1/hook',
    'localhost' => 'https://localhost/hook',
    'private class A' => 'https://10.0.0.5/hook',
    'private class B' => 'https://172.16.4.1/hook',
    'private class C' => 'https://192.168.1.10/hook',
    'link-local metadata' => 'https://169.254.169.254/latest/meta-data/',
    'ipv6 loopback' => 'https://[::1]/hook',
    'ipv6 unique-local' => 'https://[fd00::1]/hook',
    'embedded credentials' => 'https://user:pass@hooks.example.com/hook',
]);

it('fails validation without any subscribed events', function (): void {
    $company = workspace();
    $user = memberWith(['api.webhooks.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('api.webhooks.store'), ['url' => 'https://hooks.example.com/inbound', 'events' => []])
        ->assertSessionHasErrors('events');
});

it('rejects an unregistered event name', function (): void {
    $company = workspace();
    $user = memberWith(['api.webhooks.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('api.webhooks.store'), ['url' => 'https://hooks.example.com/inbound', 'events' => ['not.registered']])
        ->assertSessionHasErrors('events.0');
});

it('cannot update an endpoint from another workspace', function (): void {
    $other = workspace();
    $foreign = WebhookEndpoint::factory()->create(['company_id' => $other->id]);

    $company = workspace();
    $user = memberWith(['api.webhooks.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->patch(route('api.webhooks.update', $foreign->id), ['description' => 'hijacked'])
        ->assertNotFound();
});

it('signs the delivery body and rejects a tampered one', function (): void {
    $secret = WebhookSignature::generateSecret();
    $body = '{"id":1,"event":"user.created"}';
    $timestamp = time();

    $signature = WebhookSignature::sign($body, $secret, $timestamp);

    expect(WebhookSignature::verify($body, $secret, $timestamp, $signature))->toBeTrue()
        ->and(WebhookSignature::verify($body.' ', $secret, $timestamp, $signature))->toBeFalse()
        ->and(WebhookSignature::verify($body, 'whsec_wrong', $timestamp, $signature))->toBeFalse();
});

it('rejects a replayed signature outside the tolerance window', function (): void {
    $secret = WebhookSignature::generateSecret();
    $body = '{"id":1}';
    $timestamp = time() - (WebhookSignature::TOLERANCE + 60);

    $signature = WebhookSignature::sign($body, $secret, $timestamp);

    expect(WebhookSignature::verify($body, $secret, $timestamp, $signature))->toBeFalse();
});

it('queues a delivery for every subscribed endpoint', function (): void {
    Queue::fake();

    $company = workspace();
    WebhookEndpoint::factory()->create(['company_id' => $company->id, 'events' => ['user.created']]);
    WebhookEndpoint::factory()->create(['company_id' => $company->id, 'events' => ['user.deleted']]);

    $deliveries = app(WebhookDispatcher::class)->dispatch('user.created', ['id' => 7], $company->id);

    expect($deliveries)->toHaveCount(1);
    Queue::assertPushed(DeliverWebhook::class, 1);
});

it('does not dispatch an unregistered event', function (): void {
    Queue::fake();

    $company = workspace();
    WebhookEndpoint::factory()->create(['company_id' => $company->id, 'events' => ['user.created']]);

    expect(app(WebhookDispatcher::class)->dispatch('made.up', [], $company->id))->toBe([]);
    Queue::assertNotPushed(DeliverWebhook::class);
});

it('delivers a webhook with a verifiable signature', function (): void {
    Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);

    $company = workspace();
    $endpoint = WebhookEndpoint::factory()->create(['company_id' => $company->id, 'events' => ['user.created']]);
    $delivery = WebhookDelivery::factory()->create([
        'company_id' => $company->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    (new DeliverWebhook($delivery->id))->handle();

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Delivered)
        ->and($delivery->status_code)->toBe(200);

    Http::assertSent(function ($request) use ($endpoint): bool {
        $header = (string) config('saas.api.webhooks.signature_header');
        $timestamp = (int) $request->header(WebhookSignature::TIMESTAMP_HEADER)[0];

        return WebhookSignature::verify($request->body(), $endpoint->secret, $timestamp, $request->header($header)[0]);
    });
});

it('marks a failed delivery for retry and counts the failure', function (): void {
    Queue::fake();
    Http::fake(['hooks.example.com/*' => Http::response('boom', 500)]);

    $company = workspace();
    $endpoint = WebhookEndpoint::factory()->create(['company_id' => $company->id]);
    $delivery = WebhookDelivery::factory()->create([
        'company_id' => $company->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    (new DeliverWebhook($delivery->id))->handle();

    $delivery->refresh();
    $endpoint->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Retrying)
        ->and($delivery->next_retry_at)->not->toBeNull()
        ->and($endpoint->failure_count)->toBe(1);
});

it('auto-disables an endpoint after repeated failures', function (): void {
    Queue::fake();
    Http::fake(['hooks.example.com/*' => Http::response('boom', 500)]);

    $company = workspace();
    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->id,
        'failure_count' => WebhookEndpoint::FAILURE_THRESHOLD - 1,
    ]);
    $delivery = WebhookDelivery::factory()->create([
        'company_id' => $company->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    (new DeliverWebhook($delivery->id))->handle();

    $endpoint->refresh();

    expect($endpoint->is_active)->toBeFalse()->and($endpoint->disabled_at)->not->toBeNull();
});

it('redelivers a delivery from the UI', function (): void {
    Queue::fake();

    $company = workspace();
    $user = memberWith(['api.webhooks.manage'], $company)->refresh();
    $endpoint = WebhookEndpoint::factory()->create(['company_id' => $company->id]);
    $delivery = WebhookDelivery::factory()->create([
        'company_id' => $company->id,
        'webhook_endpoint_id' => $endpoint->id,
        'status' => WebhookDeliveryStatus::Failed,
    ]);

    actingAsMember($user, $company)
        ->post(route('api.webhooks.deliveries.redeliver', $delivery->id))
        ->assertRedirect();

    Queue::assertPushed(DeliverWebhook::class);
    expect($delivery->refresh()->status)->toBe(WebhookDeliveryStatus::Pending);
});
