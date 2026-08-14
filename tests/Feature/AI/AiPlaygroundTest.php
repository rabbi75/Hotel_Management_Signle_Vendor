<?php

declare(strict_types=1);

use App\Modules\AI\Enums\GenerationStatus;
use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Services\CreditManager;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'saas.ai.default' => 'openai',
        'saas.ai.providers.openai.key' => 'sk-openai-test',
    ]);
});

it('redirects an unauthenticated visitor', function (): void {
    $this->get(route('ai.index'))->assertRedirect(route('login'));
});

it('forbids a member without ai.use', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)->get(route('ai.index'), inertiaHeaders())->assertForbidden();
});

it('renders the playground with providers and the credit meter', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    $response = actingAsMember($user, $company)
        ->get(route('ai.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'ai/index');

    expect($response->json('props.providers'))->not->toBeEmpty()
        ->and($response->json('props.credits.available'))->toBeGreaterThan(0);

    // No provider credential ever reaches the client.
    expect(json_encode($response->json('props.providers')))->not->toContain('sk-openai-test');
});

it('runs a generation and charges credits once', function (): void {
    Http::fake(['api.openai.com/*' => Http::response([
        'model' => 'gpt-4o',
        'choices' => [['message' => ['content' => 'Generated text'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 1000],
    ])]);

    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    $response = actingAsMember($user, $company)->postJson(route('ai.generate'), ['prompt' => 'Say something.']);

    $response->assertOk()->assertJsonPath('generation.output', 'Generated text');

    $generation = AiGeneration::query()->firstOrFail();

    expect($generation->status)->toBe(GenerationStatus::Completed)
        ->and($generation->credits_charged)->toBe(4)
        ->and(app(CreditManager::class)->balance($company->id)->refresh()->used)->toBe(4);
});

it('does not consume credits when the provider call fails', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Upstream exploded']], 500)]);

    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    $before = app(CreditManager::class)->balance($company->id);

    actingAsMember($user, $company)
        ->postJson(route('ai.generate'), ['prompt' => 'Say something.'])
        ->assertStatus(422);

    $after = app(CreditManager::class)->balance($company->id)->refresh();

    expect($after->used)->toBe($before->used)
        ->and($after->reserved)->toBe(0)
        ->and(AiGeneration::query()->firstOrFail()->status)->toBe(GenerationStatus::Failed);
});

it('refuses a generation when the workspace is out of credits', function (): void {
    Http::fake();

    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    AiCreditBalance::factory()->create(['company_id' => $company->id, 'allowance' => 1, 'used' => 1]);

    $response = actingAsMember($user, $company)
        ->postJson(route('ai.generate'), ['prompt' => 'Say something.'])
        ->assertStatus(422);

    expect($response->json('message'))->toContain('0 AI credit(s) left this month');

    Http::assertNothingSent();
});

it('fails validation without a prompt or template', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)
        ->postJson(route('ai.generate'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('prompt');
});

it('renders a template and substitutes its variables', function (): void {
    Http::fake(['api.openai.com/*' => Http::response([
        'model' => 'gpt-4o',
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
    ])]);

    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    $template = AiPromptTemplate::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'prompt' => 'Greet {{name}} in {{language}}.',
        'is_shared' => true,
    ]);

    actingAsMember($user, $company)->postJson(route('ai.generate'), [
        'template_id' => $template->id,
        'variables' => ['name' => 'Ada', 'language' => 'French'],
    ])->assertOk();

    expect(AiGeneration::query()->firstOrFail()->input)->toBe('Greet Ada in French.');
});

it('cannot use a template from another workspace', function (): void {
    $other = workspace();
    $foreign = AiPromptTemplate::factory()->create(['company_id' => $other->id]);

    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)
        ->postJson(route('ai.generate'), ['prompt' => 'Hi', 'template_id' => $foreign->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('template_id');
});

it('streams a generation over server-sent events', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n\n"
        ."data: {\"choices\":[{\"delta\":{\"content\":\"lo\"},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":5,\"completion_tokens\":2}}\n\n"
        ."data: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    $response = actingAsMember($user, $company)->post(route('ai.stream'), ['prompt' => 'Say hello.']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $body = $response->streamedContent();

    expect($body)->toContain('event: delta')
        ->and($body)->toContain('Hel')
        ->and($body)->toContain('event: done');

    expect(AiGeneration::query()->firstOrFail()->output)->toBe('Hello');
});
