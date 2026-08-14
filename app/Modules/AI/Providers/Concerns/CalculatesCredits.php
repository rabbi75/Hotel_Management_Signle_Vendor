<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers\Concerns;

/**
 * Shared credit arithmetic. Cost is expressed in whole credits per 1,000
 * tokens, rounded up: a workspace should never be able to run a long tail of
 * sub-1k calls for free.
 */
trait CalculatesCredits
{
    public function estimateCost(int $promptTokens, int $completionTokens): int
    {
        $input = (int) config('saas.ai.credits.per_1k_input');
        $output = (int) config('saas.ai.credits.per_1k_output');

        return (int) ceil($promptTokens / 1000 * $input) + (int) ceil($completionTokens / 1000 * $output);
    }
}
