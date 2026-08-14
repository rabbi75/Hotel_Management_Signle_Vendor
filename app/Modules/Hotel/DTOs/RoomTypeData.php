<?php

declare(strict_types=1);

namespace App\Modules\Hotel\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class RoomTypeData extends Data
{
    public const FIELDS = [
        'hotel_id', 'name', 'code', 'description', 'base_price',
        'max_adults', 'max_children', 'max_occupancy', 'bed_configuration', 'is_active', 'facility_ids',
    ];

    /**
     * @param  list<int>  $facilityIds
     * @param  list<string>  $provided
     */
    public function __construct(
        public int $hotelId,
        public string $name,
        public ?string $code = null,
        public ?string $description = null,
        public int $basePrice = 0,
        public int $maxAdults = 2,
        public int $maxChildren = 0,
        public int $maxOccupancy = 2,
        public ?string $bedConfiguration = null,
        public bool $isActive = true,
        public array $facilityIds = [],
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));

        $facilityIds = array_values(array_filter(array_map(
            static fn ($id): int => (int) $id,
            (array) $request->input('facility_ids', []),
        )));

        return new self(
            hotelId: (int) $request->input('hotel_id'),
            name: (string) $request->string('name'),
            code: $request->input('code') ?: null,
            description: $request->input('description') ?: null,
            basePrice: (int) $request->input('base_price', 0),
            maxAdults: (int) $request->input('max_adults', 2),
            maxChildren: (int) $request->input('max_children', 0),
            maxOccupancy: (int) $request->input('max_occupancy', 2),
            bedConfiguration: $request->input('bed_configuration') ?: null,
            isActive: $request->boolean('is_active', true),
            facilityIds: $facilityIds,
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
            'description' => $this->description,
            'base_price' => $this->basePrice,
            'max_adults' => $this->maxAdults,
            'max_children' => $this->maxChildren,
            'max_occupancy' => $this->maxOccupancy,
            'bed_configuration' => $this->bedConfiguration,
            'is_active' => $this->isActive,
        ];
    }

    /** @return array<string, mixed> */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip(array_diff($this->provided, ['facility_ids'])));
    }
}