<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Requests;

use App\Modules\Folio\Enums\GuestPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folio = $this->route('folio');

        return $folio !== null && ($this->user()?->can('recordPayment', $folio) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', Rule::enum(GuestPaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
