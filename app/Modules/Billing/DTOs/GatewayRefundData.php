<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Actions\RefundTransaction;
use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;

/**
 * A refund as the processor describes it.
 *
 * Like every gateway DTO it only reports remote state; persisting the matching
 * `Transaction` is {@see RefundTransaction}'s job,
 * so the kit's own tables stay the source of truth.
 */
readonly class GatewayRefundData extends Data
{
    public function __construct(
        public string $gateway,
        public ?string $gatewayId,
        /** Minor units actually refunded, which may be less than was requested. */
        public int $amount,
        public string $currency,
        public bool $succeeded = true,
        public ?CarbonImmutable $processedAt = null,
        public ?string $failureReason = null,
    ) {}
}
