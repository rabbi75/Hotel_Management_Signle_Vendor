<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;

readonly class GatewayInvoiceData extends Data
{
    public function __construct(
        public string $gateway,
        public ?string $gatewayId,
        public string $number,
        public InvoiceStatus $status,
        public int $total,
        public string $currency,
        public ?CarbonImmutable $issuedAt = null,
        public ?CarbonImmutable $paidAt = null,
        public ?string $downloadUrl = null,
    ) {}
}
