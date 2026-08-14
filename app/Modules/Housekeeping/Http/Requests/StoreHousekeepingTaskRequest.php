<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Http\Requests;

use App\Modules\Housekeeping\Enums\HousekeepingPriority;
use App\Modules\Housekeeping\Enums\HousekeepingTaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHousekeepingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('housekeeping.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'priority' => ['nullable', 'string', Rule::enum(HousekeepingPriority::class)],
            'task_type' => ['nullable', 'string', Rule::enum(HousekeepingTaskType::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'instructions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }
}
