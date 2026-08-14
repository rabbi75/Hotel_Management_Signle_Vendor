<?php

declare(strict_types=1);

namespace App\Modules\Hotel\DTOs;

use App\Modules\Hotel\Enums\BedStatus;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class BedData extends Data
{
    public const FIELDS = [
        'hotel_id', 'room_id', 'floor_id', 'name', 'code', 'bed_type',
        'price', 'description', 'status', 'is_active',
    ];

    /** @param list<string> $provided */
    public function __construct(
        public int $hotelId,
        public int $roomId,
        public string $name,
        public ?int $floorId = null,
        public ?string $code = null,
        public ?string $bedType = null,
        public int $price = 0,
        public ?string $description = null,
        public BedStatus $status = BedStatus::Available,
        public bool $isActive = true,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));
        $floorId = $request->input('floor_id');
        $status = (string) $request->input('status', BedStatus::Available->value);

        return new self(
            hotelId: (int) $request->input('hotel_id'),
            roomId: (int) $request->input('room_id'),
            name: (string) $request->string('name'),
            floorId: is_numeric($floorId) ? (int) $floorId : null,
            code: $request->input('code') ?: null,
            bedType: $request->input('bed_type') ?: null,
            price: (int) $request->input('price', 0),
            description: $request->input('description') ?: null,
            status: BedStatus::tryFrom($status) ?? BedStatus::Available,
            isActive: $request->boolean('is_active', true),
            provided: $provided,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'hotel_id' => $this->hotelId,
            'room_id' => $this->roomId,
            'floor_id' => $this->floorId,
            'name' => $this->name,
            'code' => $this->code,
            'bed_type' => $this->bedType,
            'price' => $this->price,
            'description' => $this->description,
            'status' => $this->status,
            'is_active' => $this->isActive,
        ];
    }

    /** @return array<string, mixed> */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }
}