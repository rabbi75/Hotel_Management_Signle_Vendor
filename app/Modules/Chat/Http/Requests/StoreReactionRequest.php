<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Models\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('message');

        return $message instanceof Message && Gate::allows('react', $message);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Capped rather than validated against an emoji list: the picker is
            // a client concern, and the column is 32 characters wide.
            'emoji' => ['required', 'string', 'max:32'],
        ];
    }
}
