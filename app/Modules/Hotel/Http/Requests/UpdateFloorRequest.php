<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Requests;

use App\Modules\Hotel\Models\Floor;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFloorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $model = $this->route('floor');

        return $user instanceof User
            && $model instanceof Floor
            && $user->can('update', $model);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['sometimes', 'required', 'integer', Rule::exists('hotels', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'building_id' => ['nullable', 'integer', Rule::exists('hotel_buildings', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'floor_number' => ['sometimes', 'required', 'integer', 'min:-5', 'max:200'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}