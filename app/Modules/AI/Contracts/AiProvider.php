<?php

declare(strict_types=1);

namespace App\Modules\AI\Contracts;

use App\Modules\AI\DTOs\AiRequest;
use App\Modules\AI\DTOs\AiResponse;
use Generator;

/**
 * The single seam every model vendor is reached through.
 *
 * Drivers differ wildly underneath — one uses a vendor SDK, the rest speak HTTP
 * — but nothing above this interface may know which.
 */
interface AiProvider
{
    public function key(): string;

    public function label(): string;

    /**
     * Model ids this provider exposes, newest/most capable first.
     *
     * @return list<array{id: string, label: string}>
     */
    public function models(): array;

    public function isConfigured(): bool;

    public function generate(AiRequest $request): AiResponse;

    /**
     * Incremental text chunks. The generator's return value is the completed
     * {@see AiResponse}, so a caller that needs usage totals can read them from
     * `$generator->getReturn()` once the stream is exhausted.
     *
     * @return Generator<int, string, void, AiResponse>
     */
    public function stream(AiRequest $request): Generator;

    /**
     * Credits (not currency) a call of this size costs, using the workspace's
     * configured per-1k rates.
     */
    public function estimateCost(int $promptTokens, int $completionTokens): int;
}
