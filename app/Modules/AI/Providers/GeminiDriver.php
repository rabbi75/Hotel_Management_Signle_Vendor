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
 * Google Gemini, whose `generateContent` shape shares nothing with the others.
 *
 * All of that asymmetry is contained here: the ProviderManager and every caller
 * above it see only {@see AiProvider}.
 */
class GeminiDriver implements AiProvider
{
    use CalculatesCredits;

    public function __construct(protected ProviderKeyStore $keys) {}

    public function key(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Google Gemini';
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function models(): array
    {
        return [
            ['id' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash'],
            ['id' => 'gemini-1.5-pro', 'label' => 'Gemini 1.5 Pro'],
        ];
    }

    public function isConfigured(): bool
    {
        return $this->keys->isSet($this->key());
    }

    public function generate(AiRequest $request): AiResponse
    {
        $startedAt = microtime(true);
        $model = $this->modelFor($request);

        $response = $this->http()->post("/models/{$model}:generateContent", $this->payload($request));

        if ($response->failed()) {
            throw new AiException($this->errorMessage($response->json(), $response->status()));
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        return new AiResponse(
            provider: $this->key(),
            model: $model,
            text: $this->extractText($body),
            promptTokens: (int) data_get($body, 'usageMetadata.promptTokenCount', 0),
            completionTokens: (int) data_get($body, 'usageMetadata.candidatesTokenCount', 0),
            stopReason: $this->normaliseFinishReason(data_get($body, 'candidates.0.finishReason')),
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

        $response = $this->http()
            ->withOptions(['stream' => true])
            ->post("/models/{$model}:streamGenerateContent?alt=sse", $this->payload($request));

        if ($response->failed()) {
            throw new AiException($this->errorMessage($response->json(), $response->status()));
        }

        $text = '';
        $promptTokens = 0;
        $completionTokens = 0;
        $stopReason = null;

        foreach ($this->readSse($response->toPsrResponse()->getBody()) as $event) {
            $chunk = $this->extractText($event);

            if ($chunk !== '') {
                $text .= $chunk;

                yield $chunk;
            }

            $promptTokens = (int) data_get($event, 'usageMetadata.promptTokenCount', $promptTokens);
            $completionTokens = (int) data_get($event, 'usageMetadata.candidatesTokenCount', $completionTokens);
            $stopReason = $this->normaliseFinishReason(data_get($event, 'candidates.0.finishReason')) ?? $stopReason;
        }

        return new AiResponse(
            provider: $this->key(),
            model: $model,
            text: $text,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            stopReason: $stopReason,
            reasoning: null,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function extractText(array $body): string
    {
        $parts = data_get($body, 'candidates.0.content.parts');
        $text = '';

        foreach (is_array($parts) ? $parts : [] as $part) {
            $value = is_array($part) ? ($part['text'] ?? null) : null;

            if (is_string($value)) {
                $text .= $value;
            }
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(AiRequest $request): array
    {
        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $request->prompt]]]],
            'generationConfig' => array_filter([
                'maxOutputTokens' => $request->resolvedMaxTokens(),
                'temperature' => $request->temperature,
            ], static fn (mixed $value): bool => $value !== null),
        ];

        if ($request->system !== null && $request->system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $request->system]]];
        }

        return $payload;
    }

    protected function http(): PendingRequest
    {
        $key = $this->keys->resolve($this->key());

        if ($key === null) {
            throw ProviderNotConfiguredException::for($this->label());
        }

        $url = config('saas.ai.providers.gemini.base_url');

        return Http::baseUrl(is_string($url) ? rtrim($url, '/') : '')
            ->withHeaders(['x-goog-api-key' => $key])
            ->timeout((int) config('saas.ai.timeout'))
            ->acceptJson()
            ->asJson();
    }

    protected function modelFor(AiRequest $request): string
    {
        if ($request->model !== null && $request->model !== '') {
            return $request->model;
        }

        $model = config('saas.ai.providers.gemini.model');

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

                $decoded = json_decode(trim(substr($line, 5)), true);

                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    yield $decoded;
                }
            }
        }
    }

    protected function normaliseFinishReason(mixed $reason): ?string
    {
        if (! is_string($reason)) {
            return null;
        }

        return match ($reason) {
            'MAX_TOKENS' => 'max_tokens',
            'SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT' => 'refusal',
            'STOP' => 'end_turn',
            default => mb_strtolower($reason),
        };
    }

    protected function errorMessage(mixed $body, int $status): string
    {
        $message = is_array($body) ? data_get($body, 'error.message') : null;

        return is_string($message) && $message !== ''
            ? $message
            : __(':provider returned HTTP :status.', ['provider' => $this->label(), 'status' => $status]);
    }
}
