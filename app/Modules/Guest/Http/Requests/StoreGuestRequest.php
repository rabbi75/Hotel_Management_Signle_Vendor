<?php

declare(strict_types=1);

namespace App\Modules\Guest\Http\Requests;

use App\Modules\Guest\Enums\GuestGender;
use App\Modules\Guest\Models\Guest;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Guest::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', Rule::enum(GuestGender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'nationality' => ['nullable', 'string', 'max:120'],
            'id_type' => ['nullable', 'string', 'max:64'],
            'id_number' => ['nullable', 'string', 'max:128'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_vip' => ['sometimes', 'boolean'],
            'is_blacklisted' => ['sometimes', 'boolean'],
        ];
    }
}
