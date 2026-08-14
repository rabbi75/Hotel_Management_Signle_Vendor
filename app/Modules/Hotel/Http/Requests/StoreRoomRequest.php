<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Requests;

use App\Modules\Hotel\Models\Room;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Room::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', Rule::exists('hotels', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'building_id' => ['nullable', 'integer', Rule::exists('hotel_buildings', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'floor_id' => ['nullable', 'integer', Rule::exists('hotel_floors', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'room_type_id' => ['nullable', 'integer', Rule::exists('room_types', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'number' => ['required', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_price' => ['nullable', 'integer', 'min:0'],
            'max_occupancy' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', Rule::enum(\App\Modules\Hotel\Enums\RoomStatus::class)],
            'is_active' => ['sometimes', 'boolean'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', Rule::exists('facilities', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
        ];
    }
}