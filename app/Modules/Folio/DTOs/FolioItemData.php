<?php

declare(strict_types=1);

namespace App\Modules\Folio\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class FolioItemData extends Data
{
    public const FIELDS = ['hotel_service_id', 'description', 'quantity', 'unit_price', 'amount'];

    /** @param list<string> $provided */
    public function __construct(
        public ?int $hotelServiceId = null,
        public ?string $description = null,
        public int $quantity = 1,
        public int $unitPrice = 0,
        public ?int $amount = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));
        $serviceId = $request->input('hotel_service_id');

        return new self(
            hotelServiceId: is_numeric($serviceId) ? (int) $serviceId : null,
            description: $request->input('description') ?: null,
            quantity: max(1, (int) $request->integer('quantity', 1)),
            unitPrice: (int) $request->integer('unit_price'),
            amount: $request->has('amount') ? (int) $request->integer('amount') : null,
            provided: $provided,
        );
    }

    public function resolvedAmount(): int
    {
        return $this->amount ?? ($this->unitPrice * $this->quantity);
    }
}
