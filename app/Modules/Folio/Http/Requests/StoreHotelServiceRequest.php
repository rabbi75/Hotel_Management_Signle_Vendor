<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Requests;

use App\Modules\Folio\Enums\HotelServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hotel_services.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
            'category' => ['required', 'string', Rule::enum(HotelServiceCategory::class)],
            'price' => ['required', 'integer', 'min:0'],
            'tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
