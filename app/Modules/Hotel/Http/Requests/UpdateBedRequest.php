<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Requests;

use App\Modules\Hotel\Models\Bed;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $model = $this->route('bed');

        return $user instanceof User
            && $model instanceof Bed
            && $user->can('update', $model);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['sometimes', 'required', 'integer', Rule::exists('hotels', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'room_id' => ['sometimes', 'required', 'integer', Rule::exists('rooms', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'floor_id' => ['nullable', 'integer', Rule::exists('hotel_floors', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:64'],
            'bed_type' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(\App\Modules\Hotel\Enums\BedStatus::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}