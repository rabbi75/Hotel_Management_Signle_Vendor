<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Requests;

use App\Modules\Maintenance\Enums\MaintenanceCategory;
use App\Modules\Maintenance\Enums\MaintenancePriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'bed_id' => ['nullable', 'integer', 'exists:beds,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', Rule::enum(MaintenanceCategory::class)],
            'priority' => ['nullable', 'string', Rule::enum(MaintenancePriority::class)],
            'blocks_room' => ['sometimes', 'boolean'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
