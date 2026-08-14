<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFolioItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folio = $this->route('folio');

        return $folio !== null && ($this->user()?->can('addCharge', $folio) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hotel_service_id' => ['nullable', 'integer', 'exists:hotel_services,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'unit_price' => ['nullable', 'integer', 'min:0'],
            'amount' => ['nullable', 'integer'],
        ];
    }
}
