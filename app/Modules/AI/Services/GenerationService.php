<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\AI\Contracts\AiProvider;
use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\DTOs\AiResponse;
use App\Modules\AI\Enums\GenerationStatus;
use App\Modules\AI\Exceptions\AiException;
use App\Modules\AI\Models\AiCreditTransaction;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\User\Models\User;
use Generator;
use Throwable;

/**
 * Runs one generation end to end: hold credits, call the provider, persist the
 * outcome, then settle or release the hold.
 *
 * Credits are only ever converted into a charge on a response that actually
 * produced billable tokens, so a provider outage is free to the customer.
 */
class GenerationService
{
    public function __construct(
        protected ProviderManager $providers,
        protected CreditManager $credits,
    ) {}

    public function generate(AiRequest $request, User $user, ?string $providerKey = null, ?AiPromptTemplate $template = null): AiGeneration
    {
        $this->ensureEnabled();

        $provider = $this->providers->driver($providerKey);
        $generation = $this->start($request, $user, $provider, $template);
        $reservation = $this->reserve($request, $provider, $user);

        try {
            $response = $provider->generate($request);
        } catch (Throwable $exception) {
            $this->credits->release($reservation);
            $this->markFailed($generation, $exception->getMessage());

            throw $exception;
        }

        return $this->complete($generation, $response, $provider, $reservation);
    }

    /**
     * Streaming variant. Text chunks are yielded to the caller as they arrive;
     * the credit settlement happens once, after the stream closes.
     *
     * @return Generator<int, string, void, AiGeneration>
     */
    public function stream(AiRequest $request, User $user, ?string $providerKey = null, ?AiPromptTemplate $template = null): Generator
    {
        $this->ensureEnabled();

        $provider = $this->providers->driver($providerKey);
        $generation = $this->start($request->streaming(), $user, $provider, $template);
        $generation->forceFill(['status' => GenerationStatus::Streaming])->save();

        $reservation = $this->reserve($request, $provider, $user);

        try {
            $stream = $provider->stream($request->streaming());

            foreach ($stream as $chunk) {
                yield $chunk;
            }

            $response = $stream->getReturn();
        } catch (Throwable $exception) {
            $this->credits->release($reservation);
            $this->markFailed($generation, $exception->getMessage());

            throw $exception;
        }

        return $this->complete($generation, $response, $provider, $reservation);
    }

    protected function start(AiRequest $request, User $user, AiProvider $provider, ?AiPromptTemplate $template): AiGeneration
    {
        $generation = new AiGeneration([
            'company_id' => current_company_id(),
            'user_id' => $user->id,
            'template_id' => $template?->id,
            'provider' => $provider->key(),
            'model' => $request->model ?? ($provider->models()[0]['id'] ?? ''),
            'input' => $request->prompt,
            'output' => null,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'credits_charged' => 0,
            'duration_ms' => 0,
            'status' => GenerationStatus::Pending,
            'stop_reason' => null,
            'error' => null,
        ]);

        $generation->save();

        $template?->increment('usage_count');

        return $generation;
    }

    protected function reserve(AiRequest $request, AiProvider $provider, User $user): AiCreditTransaction
    {
        // Worst case: the whole prompt plus a full max_tokens completion. Held
        // up front so a workspace can never start a call it cannot pay for.
        $estimatedPrompt = (int) ceil(mb_strlen($request->prompt.($request->system ?? '')) / 4);

        return $this->credits->reserve(
            $provider->estimateCost($estimatedPrompt, $request->resolvedMaxTokens()),
            (int) current_company_id(),
            $user->id,
        );
    }

    protected function complete(AiGeneration $generation, AiResponse $response, AiProvider $provider, AiCreditTransaction $reservation): AiGeneration
    {
        $status = $response->status();

        // A refusal produced no billable completion; the hold goes back rather
        // than being converted into a charge.
        if ($status === GenerationStatus::Refused && $response->completionTokens === 0) {
            $this->credits->release($reservation);
            $charged = 0;
        } else {
            $charged = $provider->estimateCost($response->promptTokens, $response->completionTokens);
            $this->credits->settle($reservation, $charged, $generation->id);
        }

        $generation->forceFill([
            'model' => $response->model,
            'output' => $response->text,
            'prompt_tokens' => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'credits_charged' => $charged,
            'duration_ms' => $response->durationMs,
            'status' => $status,
            'stop_reason' => $response->stopReason,
            'error' => null,
        ])->save();

        return $generation;
    }

    protected function markFailed(AiGeneration $generation, string $error): void
    {
        $generation->forceFill([
            'status' => GenerationStatus::Failed,
            'error' => mb_substr($error, 0, 2000),
            'credits_charged' => 0,
        ])->save();
    }

    protected function ensureEnabled(): void
    {
        if (setting('ai.enabled', true) === false) {
            throw new AiException('AI is disabled for this organization.');
        }
    }
}
