<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Requests;

use App\Modules\Hotel\Models\RoomType;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', RoomType::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', Rule::exists('hotels', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_price' => ['nullable', 'integer', 'min:0'],
            'max_adults' => ['nullable', 'integer', 'min:1', 'max:50'],
            'max_children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'max_occupancy' => ['nullable', 'integer', 'min:1', 'max:100'],
            'bed_configuration' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', Rule::exists('facilities', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
        ];
    }
}