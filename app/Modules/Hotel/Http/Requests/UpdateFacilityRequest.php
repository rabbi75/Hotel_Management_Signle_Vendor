<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Requests;

use App\Modules\Hotel\Models\Facility;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $model = $this->route('facility');

        return $user instanceof User
            && $model instanceof Facility
            && $user->can('update', $model);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}