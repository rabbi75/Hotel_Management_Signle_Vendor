<?php

declare(strict_types=1);

namespace App\Modules\Hotel\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class FloorData extends Data
{
    public const FIELDS = ['hotel_id', 'building_id', 'name', 'floor_number', 'code', 'description', 'is_active'];

    /** @param list<string> $provided */
    public function __construct(
        public int $hotelId,
        public string $name,
        public int $floorNumber = 0,
        public ?int $buildingId = null,
        public ?string $code = null,
        public ?string $description = null,
        public bool $isActive = true,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));
        $buildingId = $request->input('building_id');

        return new self(
            hotelId: (int) $request->input('hotel_id'),
            name: (string) $request->string('name'),
            floorNumber: (int) $request->input('floor_number', 0),
            buildingId: is_numeric($buildingId) ? (int) $buildingId : null,
            code: $request->input('code') ?: null,
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
            'building_id' => $this->buildingId,
            'name' => $this->name,
            'floor_number' => $this->floorNumber,
            'code' => $this->code,
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