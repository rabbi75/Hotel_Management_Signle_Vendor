<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Messages\Message;
use Anthropic\Messages\OutputConfig;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ThinkingBlock;
use Anthropic\Messages\ThinkingConfigAdaptive;
use Anthropic\Messages\ThinkingConfigDisabled;
use App\Modules\AI\Contracts\AiProvider;
use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\DTOs\AiResponse;
use App\Modules\AI\Exceptions\AiException;
use App\Modules\AI\Exceptions\ProviderNotConfiguredException;
use App\Modules\AI\Providers\Concerns\CalculatesCredits;
use App\Modules\AI\Services\ProviderKeyStore;
use App\Modules\AI\Support\AnthropicClientFactory;
use Generator;

/**
 * Claude, through the official `anthropic-ai/sdk` package.
 *
 * The current Opus model's request surface differs from older Claude material
 * in ways that are hard failures rather than warnings, and this driver is the
 * only place that knows it:
 *
 *  - extended thinking with a fixed `budget_tokens` is gone; depth is adaptive
 *    thinking plus `output_config.effort`;
 *  - `temperature`, `top_p` and `top_k` are rejected outright, so the sampling
 *    knob an AiRequest may carry for other vendors is dropped here;
 *  - assistant-turn prefills are rejected, so shaping output means structured
 *    outputs, not a primed assistant message;
 *  - `stop_reason` must be inspected before the content is read: `refusal` is a
 *    normal outcome that arrives with empty or partial content.
 */
class AnthropicDriver implements AiProvider
{
    use CalculatesCredits;

    public function __construct(
        protected AnthropicClientFactory $clients,
        protected ProviderKeyStore $keys,
    ) {}

    public function key(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return 'Anthropic (Claude)';
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function models(): array
    {
        return [
            ['id' => $this->defaultModel(), 'label' => 'Claude Opus 4.8'],
            ['id' => 'claude-sonnet-5', 'label' => 'Claude Sonnet 5'],
            ['id' => 'claude-haiku-4-5', 'label' => 'Claude Haiku 4.5'],
        ];
    }

    public function isConfigured(): bool
    {
        return $this->keys->isSet($this->key());
    }

    public function generate(AiRequest $request): AiResponse
    {
        $startedAt = microtime(true);

        try {
            $message = $this->client()->messages->create(
                maxTokens: $request->resolvedMaxTokens(),
                messages: [['role' => 'user', 'content' => $request->prompt]],
                model: $request->model ?? $this->defaultModel(),
                outputConfig: OutputConfig::with(effort: $this->effort()),
                system: $request->system,
                thinking: $this->thinking(),
            );
        } catch (APIException $exception) {
            throw new AiException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $this->toResponse($message, (int) round((microtime(true) - $startedAt) * 1000));
    }

    /**
     * @return Generator<int, string, void, AiResponse>
     */
    public function stream(AiRequest $request): Generator
    {
        $startedAt = microtime(true);
        $model = $request->model ?? $this->defaultModel();

        $text = '';
        $promptTokens = 0;
        $completionTokens = 0;
        $stopReason = null;

        try {
            // Always streamed: a large max_tokens on a non-streaming request is
            // the classic way to hit an HTTP timeout mid-generation.
            $stream = $this->client()->messages->createStream(
                maxTokens: $request->resolvedMaxTokens(),
                messages: [['role' => 'user', 'content' => $request->prompt]],
                model: $model,
                outputConfig: OutputConfig::with(effort: $this->effort()),
                system: $request->system,
                thinking: $this->thinking(),
            );

            foreach ($stream as $event) {
                if ($event instanceof RawMessageStartEvent) {
                    $promptTokens = $event->message->usage->inputTokens;

                    continue;
                }

                if ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    $text .= $event->delta->text;

                    yield $event->delta->text;

                    continue;
                }

                if ($event instanceof RawMessageDeltaEvent) {
                    $completionTokens = $event->usage->outputTokens;
                    $stopReason = $event->delta->stopReason ?? $stopReason;
                }
            }
        } catch (APIException $exception) {
            throw new AiException($exception->getMessage(), $exception->getCode(), $exception);
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

    protected function toResponse(Message $message, int $durationMs): AiResponse
    {
        $text = '';
        $reasoning = null;

        // stop_reason first: on a refusal the content array is empty or holds a
        // partial answer, and treating that as a normal completion would show
        // the user a blank box with no explanation.
        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $text .= $block->text;
            }

            if ($block instanceof ThinkingBlock && $block->thinking !== '') {
                $reasoning = ($reasoning ?? '').$block->thinking;
            }
        }

        return new AiResponse(
            provider: $this->key(),
            model: $message->model,
            text: $text,
            promptTokens: $message->usage->inputTokens,
            completionTokens: $message->usage->outputTokens,
            stopReason: $message->stopReason,
            reasoning: $reasoning,
            durationMs: $durationMs,
        );
    }

    protected function client(): Client
    {
        $key = $this->keys->resolve($this->key());

        if ($key === null) {
            throw ProviderNotConfiguredException::for($this->label());
        }

        $baseUrl = config('saas.ai.providers.anthropic.base_url');

        // The SDK appends its own `/v1`; config carries the versioned URL for
        // symmetry with the hand-rolled drivers, so strip it back off here.
        return $this->clients->make($key, is_string($baseUrl) ? (string) preg_replace('#/v1/?$#', '', $baseUrl) : null);
    }

    /**
     * `budget_tokens` is removed on this model family and returns a 400, so the
     * only two valid shapes are adaptive or disabled. `display: summarized` is
     * requested explicitly because the default is `omitted`, which would leave
     * the reasoning panel permanently blank.
     */
    protected function thinking(): ThinkingConfigAdaptive|ThinkingConfigDisabled
    {
        return config('saas.ai.providers.anthropic.thinking') === 'adaptive'
            ? ThinkingConfigAdaptive::with(display: 'summarized')
            : new ThinkingConfigDisabled;
    }

    protected function effort(): string
    {
        $effort = config('saas.ai.providers.anthropic.effort');
        $allowed = ['low', 'medium', 'high', 'xhigh', 'max'];

        return is_string($effort) && in_array($effort, $allowed, true) ? $effort : 'high';
    }

    protected function defaultModel(): string
    {
        $model = config('saas.ai.providers.anthropic.model');

        return is_string($model) && $model !== '' ? $model : 'claude-opus-4-8';
    }
}
