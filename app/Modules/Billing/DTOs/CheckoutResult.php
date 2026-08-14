<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A verified outcome of a hosted checkout.
 *
 * Only ever produced after the driver has confirmed the payment with the
 * processor directly. A driver that cannot verify returns null instead of an
 * unsuccessful result: "I could not check" and "the customer did not pay" are
 * different facts, and only the second should be shown to them as a failure.
 */
readonly class CheckoutResult extends Data
{
    public function __construct(
        public string $gateway,
        public string $reference,
        public bool $paid,
        /** Minor units actually captured, as the processor reports them. */
        public int $amount,
        public string $currency,
        /** The charge id a later refund is issued against. */
        public ?string $transactionId = null,
        public ?string $customerId = null,
        public ?string $failureReason = null,
    ) {}
}
