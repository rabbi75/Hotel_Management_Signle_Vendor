<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Requests;

use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Hotel::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'check_in_time' => ['nullable', 'string', 'max:8'],
            'check_out_time' => ['nullable', 'string', 'max:8'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_name' => ['nullable', 'string', 'max:120'],
            'policies' => ['nullable', 'string', 'max:10000'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', Rule::enum(HotelStatus::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}