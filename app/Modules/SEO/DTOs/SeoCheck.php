<?php

declare(strict_types=1);

namespace App\Modules\SEO\DTOs;

use App\Modules\SEO\Enums\CheckStatus;
use App\Modules\SEO\Services\SeoScorer;
use App\Support\DTOs\Data;

/**
 * One finding from {@see SeoScorer}.
 *
 * `$message` is the whole point of this object: it must name the problem and
 * the fix in the author's own terms. "Score: 62" is not a finding.
 */
readonly class SeoCheck extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public CheckStatus $status,
        public string $message,
        /** How much this check contributes to the headline score. */
        public int $weight = 1,
    ) {}

    public static function pass(string $key, string $label, string $message, int $weight = 1): self
    {
        return new self($key, $label, CheckStatus::Pass, $message, $weight);
    }

    public static function warn(string $key, string $label, string $message, int $weight = 1): self
    {
        return new self($key, $label, CheckStatus::Warn, $message, $weight);
    }

    public static function fail(string $key, string $label, string $message, int $weight = 1): self
    {
        return new self($key, $label, CheckStatus::Fail, $message, $weight);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status->value,
            'message' => $this->message,
            'weight' => $this->weight,
        ];
    }
}
