<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Requests;

use App\Modules\Hotel\Models\Building;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Building::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', Rule::exists('hotels', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}