<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * The displayable half of a stored payment instrument. Never a PAN, never a CVC.
 */
readonly class GatewayPaymentMethodData extends Data
{
    public function __construct(
        public string $gateway,
        public string $gatewayId,
        public string $type = 'card',
        public ?string $brand = null,
        public ?string $lastFour = null,
        public ?int $expMonth = null,
        public ?int $expYear = null,
        public ?string $holderName = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'gateway' => $this->gateway,
            'gateway_id' => $this->gatewayId,
            'type' => $this->type,
            'brand' => $this->brand,
            'last_four' => $this->lastFour,
            'exp_month' => $this->expMonth,
            'exp_year' => $this->expYear,
            'holder_name' => $this->holderName,
        ];
    }
}
