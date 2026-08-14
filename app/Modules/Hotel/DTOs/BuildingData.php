<?php

declare(strict_types=1);

namespace App\Modules\Hotel\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class BuildingData extends Data
{
    public const FIELDS = ['hotel_id', 'name', 'code', 'description', 'is_active'];

    /** @param list<string> $provided */
    public function __construct(
        public int $hotelId,
        public string $name,
        public ?string $code = null,
        public ?string $description = null,
        public bool $isActive = true,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));

        return new self(
            hotelId: (int) $request->input('hotel_id'),
            name: (string) $request->string('name'),
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
            'name' => $this->name,
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