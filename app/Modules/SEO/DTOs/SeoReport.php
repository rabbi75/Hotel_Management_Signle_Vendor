<?php

declare(strict_types=1);

namespace App\Modules\SEO\DTOs;

use App\Modules\SEO\Enums\CheckStatus;
use App\Support\DTOs\Data;

/**
 * The result of analysing one page.
 *
 * The score exists so a list of fifty posts can be sorted; it is never the
 * deliverable on its own. {@see self::$checks} is.
 */
readonly class SeoReport extends Data
{
    /**
     * @param  list<SeoCheck>  $checks
     */
    public function __construct(public array $checks)
    {
        //
    }

    /**
     * Weighted percentage, where a warning counts as half credit.
     */
    public function score(): int
    {
        $total = array_sum(array_map(static fn (SeoCheck $check): int => $check->weight, $this->checks));

        if ($total === 0) {
            return 0;
        }

        $earned = array_sum(array_map(static fn (SeoCheck $check): float => match ($check->status) {
            CheckStatus::Pass => (float) $check->weight,
            CheckStatus::Warn => $check->weight / 2,
            CheckStatus::Fail => 0.0,
        }, $this->checks));

        return (int) round($earned / $total * 100);
    }

    /**
     * @return list<SeoCheck>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (SeoCheck $check): bool => $check->status === CheckStatus::Fail,
        ));
    }

    public function counts(CheckStatus $status): int
    {
        return count(array_filter($this->checks, static fn (SeoCheck $check): bool => $check->status === $status));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score(),
            'passed' => $this->counts(CheckStatus::Pass),
            'warnings' => $this->counts(CheckStatus::Warn),
            'failed' => $this->counts(CheckStatus::Fail),
            'checks' => array_map(static fn (SeoCheck $check): array => $check->toArray(), $this->checks),
        ];
    }
}
