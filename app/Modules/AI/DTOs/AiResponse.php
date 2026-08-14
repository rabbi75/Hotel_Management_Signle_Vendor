<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

use App\Modules\AI\Enums\GenerationStatus;
use App\Support\DTOs\Data;

/**
 * The normalised result of a completion, whatever produced it.
 */
readonly class AiResponse extends Data
{
    /**
     * @param  string|null  $reasoning  Summarised model reasoning, when the
     *                                  provider was asked to surface it.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $text,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public ?string $stopReason = null,
        public ?string $reasoning = null,
        public int $durationMs = 0,
    ) {}

    /**
     * A refusal is a normal outcome, not an error: content is empty or partial
     * and the caller must not treat the empty string as a failed request.
     */
    public function status(): GenerationStatus
    {
        return match ($this->stopReason) {
            'refusal' => GenerationStatus::Refused,
            'max_tokens', 'length' => GenerationStatus::Truncated,
            default => GenerationStatus::Completed,
        };
    }

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
