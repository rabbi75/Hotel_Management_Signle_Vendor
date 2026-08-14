<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;

/**
 * Where to send the customer to pay, and how we will recognise them coming back.
 *
 * `reference` is the processor's own id for the attempt. It is what the return
 * leg re-fetches server-side to establish that the payment really happened —
 * the browser arriving back at a URL proves nothing by itself.
 */
readonly class CheckoutSession extends Data
{
    public function __construct(
        public string $gateway,
        public string $url,
        public string $reference,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
