<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class MaintenanceRequestData extends Data
{
    public const FIELDS = [
        'hotel_id', 'room_id', 'bed_id', 'title', 'description', 'category',
        'priority', 'blocks_room', 'assigned_to', 'due_at',
    ];

    /** @param list<string> $provided */
    public function __construct(
        public int $hotelId,
        public string $title,
        public ?int $roomId = null,
        public ?int $bedId = null,
        public ?string $description = null,
        public string $category = 'other',
        public string $priority = 'normal',
        public bool $blocksRoom = true,
        public ?int $assignedTo = null,
        public ?string $dueAt = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));

        return new self(
            hotelId: (int) $request->integer('hotel_id'),
            title: (string) $request->string('title'),
            roomId: $request->filled('room_id') ? (int) $request->integer('room_id') : null,
            bedId: $request->filled('bed_id') ? (int) $request->integer('bed_id') : null,
            description: $request->input('description') ?: null,
            category: (string) $request->string('category', 'other'),
            priority: (string) $request->string('priority', 'normal'),
            blocksRoom: $request->boolean('blocks_room', true),
            assignedTo: $request->filled('assigned_to') ? (int) $request->integer('assigned_to') : null,
            dueAt: $request->input('due_at') ?: null,
            provided: $provided,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'hotel_id' => $this->hotelId,
            'room_id' => $this->roomId,
            'bed_id' => $this->bedId,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'blocks_room' => $this->blocksRoom,
            'assigned_to' => $this->assignedTo,
            'due_at' => $this->dueAt,
        ];
    }

    /** @return array<string, mixed> */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }
}
