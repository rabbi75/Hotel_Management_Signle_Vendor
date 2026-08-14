<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignHousekeepingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('housekeepingTask');

        return $task !== null && ($this->user()?->can('assign', $task) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
