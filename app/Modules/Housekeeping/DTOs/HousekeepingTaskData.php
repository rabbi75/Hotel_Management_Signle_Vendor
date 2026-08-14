<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class HousekeepingTaskData extends Data
{
    public const FIELDS = [
        'hotel_id', 'room_id', 'reservation_id', 'priority', 'task_type',
        'assigned_to', 'instructions', 'notes', 'scheduled_for',
    ];

    /** @param list<string> $provided */
    public function __construct(
        public int $hotelId,
        public int $roomId,
        public ?int $reservationId = null,
        public string $priority = 'normal',
        public string $taskType = 'checkout',
        public ?int $assignedTo = null,
        public ?string $instructions = null,
        public ?string $notes = null,
        public ?string $scheduledFor = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));

        return new self(
            hotelId: (int) $request->integer('hotel_id'),
            roomId: (int) $request->integer('room_id'),
            reservationId: $request->filled('reservation_id') ? (int) $request->integer('reservation_id') : null,
            priority: (string) $request->string('priority', 'normal'),
            taskType: (string) $request->string('task_type', 'other'),
            assignedTo: $request->filled('assigned_to') ? (int) $request->integer('assigned_to') : null,
            instructions: $request->input('instructions') ?: null,
            notes: $request->input('notes') ?: null,
            scheduledFor: $request->input('scheduled_for') ?: null,
            provided: $provided,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'hotel_id' => $this->hotelId,
            'room_id' => $this->roomId,
            'reservation_id' => $this->reservationId,
            'priority' => $this->priority,
            'task_type' => $this->taskType,
            'assigned_to' => $this->assignedTo,
            'instructions' => $this->instructions,
            'notes' => $this->notes,
            'scheduled_for' => $this->scheduledFor,
        ];
    }
}
