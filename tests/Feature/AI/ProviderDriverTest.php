<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Modules\AI\Contracts\AiProvider;
use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\Enums\GenerationStatus;
use App\Modules\AI\Exceptions\AiException;
use App\Modules\AI\Exceptions\ProviderNotConfiguredException;
use App\Modules\AI\Providers\AnthropicDriver;
use App\Modules\AI\Providers\DeepSeekDriver;
use App\Modules\AI\Providers\GeminiDriver;
use App\Modules\AI\Providers\GrokDriver;
use App\Modules\AI\Providers\OpenAiDriver;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\AI\Support\AnthropicClientFactory;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Records the outbound request and replays a canned body, so the official
 * Anthropic SDK is exercised end to end without a live API call.
 */
function fakeAnthropicTransport(array $body, int $status = 200): ClientInterface
{
    return new class($body, $status) implements ClientInterface
    {
        /** @var list<array{uri: string, body: string}> */
        public array $sent = [];

        public function __construct(private array $body, private int $status) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->sent[] = ['uri' => (string) $request->getUri(), 'body' => (string) $request->getBody()];

            return new Psr7Response($this->status, ['Content-Type' => 'application/json'], json_encode($this->body));
        }
    };
}

function anthropicMessageBody(string $text = 'Hello from Claude', string $stopReason = 'end_turn'): array
{
    return [
        'id' => 'msg_01',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-4-8',
        'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text]],
        'stop_reason' => $stopReason,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 120, 'output_tokens' => 60],
    ];
}

function bindAnthropicTransport(ClientInterface $transport): void
{
    app()->instance(AnthropicClientFactory::class, new class($transport) extends AnthropicClientFactory
    {
        public function __construct(private ClientInterface $transport) {}

        public function make(string $apiKey, ?string $baseUrl = null): Client
        {
            return new Client(
                apiKey: $apiKey,
                baseUrl: $baseUrl,
                requestOptions: ['transporter' => $this->transport, 'maxRetries' => 0],
            );
        }
    });
}

beforeEach(function (): void {
    workspace();

    config([
        'saas.ai.providers.anthropic.key' => 'sk-ant-test',
        'saas.ai.providers.openai.key' => 'sk-openai-test',
        'saas.ai.providers.gemini.key' => 'gemini-test',
        'saas.ai.providers.deepseek.key' => 'deepseek-test',
        'saas.ai.providers.grok.key' => 'grok-test',
    ]);
});

it('calls Claude through the official SDK and never sends rejected parameters', function (): void {
    $transport = fakeAnthropicTransport(anthropicMessageBody());
    bindAnthropicTransport($transport);

    $response = app(AnthropicDriver::class)->generate(new AiRequest(
        prompt: 'Say hello.',
        system: 'You are terse.',
        temperature: 0.9,
    ));

    expect($response->text)->toBe('Hello from Claude')
        ->and($response->promptTokens)->toBe(120)
        ->and($response->completionTokens)->toBe(60)
        ->and($response->status())->toBe(GenerationStatus::Completed);

    /** @var array{uri: string, body: string} $sent */
    $sent = $transport->sent[0];
    $payload = json_decode($sent['body'], true);

    expect($sent['uri'])->toContain('/v1/messages')
        ->and($payload['model'])->toBe('claude-opus-4-8')
        // temperature / top_p / top_k are rejected on this model family, and the
        // AiRequest carried one for the other providers' benefit.
        ->and($payload)->not->toHaveKey('temperature')
        ->and($payload)->not->toHaveKey('top_p')
        ->and($payload)->not->toHaveKey('top_k')
        // Adaptive thinking, not a fixed budget, and an effort level beside it.
        ->and($payload['thinking'])->toBe(['type' => 'adaptive', 'display' => 'summarized'])
        ->and($payload['thinking'])->not->toHaveKey('budget_tokens')
        ->and($payload['output_config']['effort'])->toBe('high')
        // No assistant-turn prefill: the last message is always the user's.
        ->and($payload['messages'])->toHaveCount(1)
        ->and($payload['messages'][0]['role'])->toBe('user');
});

it('treats a Claude refusal as a normal outcome rather than an error', function (): void {
    bindAnthropicTransport(fakeAnthropicTransport(anthropicMessageBody('', 'refusal')));

    $response = app(AnthropicDriver::class)->generate(new AiRequest(prompt: 'Something disallowed.'));

    expect($response->text)->toBe('')
        ->and($response->stopReason)->toBe('refusal')
        ->and($response->status())->toBe(GenerationStatus::Refused);
});

it('reports a truncated Claude response', function (): void {
    bindAnthropicTransport(fakeAnthropicTransport(anthropicMessageBody('Partial', 'max_tokens')));

    expect(app(AnthropicDriver::class)->generate(new AiRequest(prompt: 'Write a novel.'))->status())
        ->toBe(GenerationStatus::Truncated);
});

it('calls OpenAI-compatible providers over HTTP', function (string $driver, string $host, string $expectedModel): void {
    Http::fake([
        "{$host}/*" => Http::response([
            'model' => $expectedModel,
            'choices' => [['message' => ['content' => 'Hi there'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ]),
    ]);

    /** @var AiProvider $provider */
    $provider = app($driver);
    $response = $provider->generate(new AiRequest(prompt: 'Hi', temperature: 0.3));

    expect($response->text)->toBe('Hi there')
        ->and($response->promptTokens)->toBe(10)
        ->and($response->completionTokens)->toBe(5);

    Http::assertSent(function ($request) use ($expectedModel): bool {
        $payload = $request->data();

        return $request->hasHeader('Authorization')
            && str_contains((string) $request->url(), '/chat/completions')
            && $payload['model'] === $expectedModel
            && $payload['temperature'] === 0.3;
    });
})->with([
    'openai' => [OpenAiDriver::class, 'api.openai.com', 'gpt-4o'],
    'deepseek' => [DeepSeekDriver::class, 'api.deepseek.com', 'deepseek-chat'],
    'grok' => [GrokDriver::class, 'api.x.ai', 'grok-2-latest'],
]);

it('calls Gemini with its own request shape', function (): void {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'Gemini says hi']]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 8, 'candidatesTokenCount' => 4],
        ]),
    ]);

    $response = app(GeminiDriver::class)->generate(new AiRequest(prompt: 'Hi', system: 'Be brief.'));

    expect($response->text)->toBe('Gemini says hi')
        ->and($response->stopReason)->toBe('end_turn')
        ->and($response->promptTokens)->toBe(8);

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->hasHeader('x-goog-api-key')
            && str_contains((string) $request->url(), ':generateContent')
            && isset($payload['contents'][0]['parts'][0]['text'], $payload['systemInstruction']);
    });
});

it('surfaces a provider error as an AI exception', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

    expect(fn () => app(OpenAiDriver::class)->generate(new AiRequest(prompt: 'Hi')))
        ->toThrow(AiException::class, 'Invalid API key');
});

it('refuses to run a provider with no configured key', function (): void {
    config(['saas.ai.providers.openai.key' => null]);

    expect(fn () => app(OpenAiDriver::class)->generate(new AiRequest(prompt: 'Hi')))
        ->toThrow(ProviderNotConfiguredException::class);
});

it('resolves every configured provider through the manager', function (): void {
    $manager = app(ProviderManager::class);

    expect($manager->keys())->toBe(['anthropic', 'openai', 'gemini', 'deepseek', 'grok'])
        ->and($manager->default())->toBe('anthropic')
        ->and($manager->driver()->key())->toBe('anthropic');

    $catalogue = $manager->catalogue();

    expect($catalogue[0]['is_default'])->toBeTrue()
        ->and($catalogue[0]['models'][0]['id'])->toBe('claude-opus-4-8')
        // Model ids are exact, complete strings — never date-suffixed.
        ->and($catalogue[0]['models'][0]['id'])->not->toMatch('/-\d{8}$/');
});

it('rejects an unknown provider', function (): void {
    expect(fn () => app(ProviderManager::class)->driver('nope'))
        ->toThrow(AiException::class);
});

it('charges credits from the configured per-1k rates', function (): void {
    config(['saas.ai.credits.per_1k_input' => 1, 'saas.ai.credits.per_1k_output' => 3]);

    expect(app(OpenAiDriver::class)->estimateCost(2000, 1000))->toBe(5);
});
