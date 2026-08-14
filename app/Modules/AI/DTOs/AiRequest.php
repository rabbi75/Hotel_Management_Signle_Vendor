<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

use App\Support\DTOs\Data;

/**
 * One provider-agnostic completion request.
 *
 * Deliberately carries `temperature` even though the default Anthropic model
 * rejects it: other providers still take it, and it is each driver's job to
 * translate — or drop — what its transport cannot express.
 */
readonly class AiRequest extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $prompt,
        public ?string $system = null,
        public ?string $model = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public bool $stream = false,
        public array $metadata = [],
    ) {}

    public function resolvedMaxTokens(): int
    {
        return $this->maxTokens ?? (int) config('saas.ai.max_tokens');
    }

    public function withModel(string $model): self
    {
        return new self(
            prompt: $this->prompt,
            system: $this->system,
            model: $model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            stream: $this->stream,
            metadata: $this->metadata,
        );
    }

    public function streaming(bool $stream = true): self
    {
        return new self(
            prompt: $this->prompt,
            system: $this->system,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            stream: $stream,
            metadata: $this->metadata,
        );
    }
}
