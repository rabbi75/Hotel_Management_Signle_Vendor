<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A webhook normalised into the shape the kit stores and dispatches on.
 */
readonly class GatewayWebhookData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $gateway,
        public string $eventId,
        public string $type,
        public array $payload = [],
    ) {}
}
