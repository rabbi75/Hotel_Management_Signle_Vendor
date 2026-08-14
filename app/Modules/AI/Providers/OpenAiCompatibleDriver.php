<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use App\Modules\AI\Contracts\AiProvider;
use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\DTOs\AiResponse;
use App\Modules\AI\Exceptions\AiException;
use App\Modules\AI\Exceptions\ProviderNotConfiguredException;
use App\Modules\AI\Providers\Concerns\CalculatesCredits;
use App\Modules\AI\Services\ProviderKeyStore;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Base for the vendors that expose an OpenAI-shaped `/chat/completions`
 * endpoint (OpenAI itself, DeepSeek, Grok).
 *
 * Transport lives entirely inside the driver, so callers never learn that some
 * providers are reached through a vendor SDK and others through raw HTTP.
 */
abstract class OpenAiCompatibleDriver implements AiProvider
{
    use CalculatesCredits;

    public function __construct(protected ProviderKeyStore $keys) {}

    public function isConfigured(): bool
    {
        return $this->keys->isSet($this->key());
    }

    public function generate(AiRequest $request): AiResponse
    {
        $startedAt = microtime(true);

        $response = $this->http()->post('/chat/completions', $this->payload($request, false));

        if ($response->failed()) {
            throw new AiException($this->errorMessage($response->json(), $response->status()));
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        $choice = data_get($body, 'choices.0', []);
        $usage = data_get($body, 'usage', []);

        return new AiResponse(
            provider: $this->key(),
            model: is_string(data_get($body, 'model')) ? (string) data_get($body, 'model') : $this->modelFor($request),
            text: (string) data_get($choice, 'message.content', ''),
            promptTokens: (int) data_get($usage, 'prompt_tokens', 0),
            completionTokens: (int) data_get($usage, 'completion_tokens', 0),
            stopReason: is_string(data_get($choice, 'finish_reason')) ? (string) data_get($choice, 'finish_reason') : null,
            reasoning: null,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * @return Generator<int, string, void, AiResponse>
     */
    public function stream(AiRequest $request): Generator
    {
        $startedAt = microtime(true);
        $model = $this->modelFor($request);

        $response = $this->http()->withOptions(['stream' => true])->post('/chat/completions', $this->payload($request, true));

        if ($response->failed()) {
            throw new AiException($this->errorMessage($response->json(), $response->status()));
        }

        $text = '';
        $promptTokens = 0;
        $completionTokens = 0;
        $stopReason = null;

        foreach ($this->readSse($response->toPsrResponse()->getBody()) as $event) {
            $delta = data_get($event, 'choices.0.delta.content');

            if (is_string($delta) && $delta !== '') {
                $text .= $delta;

                yield $delta;
            }

            $finish = data_get($event, 'choices.0.finish_reason');

            if (is_string($finish)) {
                $stopReason = $finish;
            }

            if (is_array(data_get($event, 'usage'))) {
                $promptTokens = (int) data_get($event, 'usage.prompt_tokens', $promptTokens);
                $completionTokens = (int) data_get($event, 'usage.completion_tokens', $completionTokens);
            }
        }

        return new AiResponse(
            provider: $this->key(),
            model: $model,
            text: $text,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens === 0 ? (int) ceil(mb_strlen($text) / 4) : $completionTokens,
            stopReason: $stopReason,
            reasoning: null,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(AiRequest $request, bool $stream): array
    {
        $messages = [];

        if ($request->system !== null && $request->system !== '') {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }

        $messages[] = ['role' => 'user', 'content' => $request->prompt];

        $payload = [
            'model' => $this->modelFor($request),
            'messages' => $messages,
            'max_tokens' => $request->resolvedMaxTokens(),
            'stream' => $stream,
        ];

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($stream) {
            $payload['stream_options'] = ['include_usage' => true];
        }

        return $payload;
    }

    protected function http(): PendingRequest
    {
        $key = $this->keys->resolve($this->key());

        if ($key === null) {
            throw ProviderNotConfiguredException::for($this->label());
        }

        return Http::baseUrl($this->baseUrl())
            ->withToken($key)
            ->timeout((int) config('saas.ai.timeout'))
            ->acceptJson()
            ->asJson();
    }

    protected function baseUrl(): string
    {
        $url = config("saas.ai.providers.{$this->key()}.base_url");

        return is_string($url) ? rtrim($url, '/') : '';
    }

    protected function modelFor(AiRequest $request): string
    {
        if ($request->model !== null && $request->model !== '') {
            return $request->model;
        }

        $model = config("saas.ai.providers.{$this->key()}.model");

        return is_string($model) ? $model : '';
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    protected function readSse(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($position = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $position));
                $buffer = substr($buffer, $position + 1);

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));

                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }

                $decoded = json_decode($payload, true);

                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    yield $decoded;
                }
            }
        }
    }

    protected function errorMessage(mixed $body, int $status): string
    {
        $message = is_array($body) ? data_get($body, 'error.message') : null;

        return is_string($message) && $message !== ''
            ? $message
            : __(':provider returned HTTP :status.', ['provider' => $this->label(), 'status' => $status]);
    }
}
