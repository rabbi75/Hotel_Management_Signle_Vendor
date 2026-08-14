<?php

declare(strict_types=1);

namespace App\Modules\Folio\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class HotelServiceData extends Data
{
    public const FIELDS = ['hotel_id', 'name', 'code', 'category', 'price', 'tax_rate', 'description', 'is_active'];

    /** @param list<string> $provided */
    public function __construct(
        public string $name,
        public ?int $hotelId = null,
        public ?string $code = null,
        public string $category = 'other',
        public int $price = 0,
        public int $taxRate = 0,
        public ?string $description = null,
        public bool $isActive = true,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));
        $hotelId = $request->input('hotel_id');

        return new self(
            name: (string) $request->string('name'),
            hotelId: is_numeric($hotelId) ? (int) $hotelId : null,
            code: $request->input('code') ?: null,
            category: (string) $request->string('category', 'other'),
            price: (int) $request->integer('price'),
            taxRate: (int) $request->integer('tax_rate'),
            description: $request->input('description') ?: null,
            isActive: $request->boolean('is_active', true),
            provided: $provided,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'hotel_id' => $this->hotelId,
            'name' => $this->name,
            'code' => $this->code,
            'category' => $this->category,
            'price' => $this->price,
            'tax_rate' => $this->taxRate,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }

    /** @return array<string, mixed> */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }
}
