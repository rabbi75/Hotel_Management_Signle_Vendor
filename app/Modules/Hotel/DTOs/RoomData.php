<?php

declare(strict_types=1);

namespace App\Modules\Hotel\DTOs;

use App\Modules\Hotel\Enums\RoomStatus;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class RoomData extends Data
{
    public const FIELDS = [
        'hotel_id', 'building_id', 'floor_id', 'room_type_id', 'number', 'code',
        'description', 'base_price', 'max_occupancy', 'status', 'is_active', 'facility_ids',
    ];

    /**
     * @param  list<int>  $facilityIds
     * @param  list<string>  $provided
     */
    public function __construct(
        public int $hotelId,
        public string $number,
        public ?int $buildingId = null,
        public ?int $floorId = null,
        public ?int $roomTypeId = null,
        public ?string $code = null,
        public ?string $description = null,
        public ?int $basePrice = null,
        public ?int $maxOccupancy = null,
        public RoomStatus $status = RoomStatus::Available,
        public bool $isActive = true,
        public array $facilityIds = [],
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));

        $nullableInt = static function (mixed $value): ?int {
            return is_numeric($value) ? (int) $value : null;
        };

        $facilityIds = array_values(array_filter(array_map(
            static fn ($id): int => (int) $id,
            (array) $request->input('facility_ids', []),
        )));

        $status = (string) $request->input('status', RoomStatus::Available->value);

        return new self(
            hotelId: (int) $request->input('hotel_id'),
            number: (string) $request->string('number'),
            buildingId: $nullableInt($request->input('building_id')),
            floorId: $nullableInt($request->input('floor_id')),
            roomTypeId: $nullableInt($request->input('room_type_id')),
            code: $request->input('code') ?: null,
            description: $request->input('description') ?: null,
            basePrice: $nullableInt($request->input('base_price')),
            maxOccupancy: $nullableInt($request->input('max_occupancy')),
            status: RoomStatus::tryFrom($status) ?? RoomStatus::Available,
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
            'building_id' => $this->buildingId,
            'floor_id' => $this->floorId,
            'room_type_id' => $this->roomTypeId,
            'number' => $this->number,
            'code' => $this->code,
            'description' => $this->description,
            'base_price' => $this->basePrice,
            'max_occupancy' => $this->maxOccupancy,
            'status' => $this->status,
            'is_active' => $this->isActive,
        ];
    }

    /** @return array<string, mixed> */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip(array_diff($this->provided, ['facility_ids'])));
    }
}