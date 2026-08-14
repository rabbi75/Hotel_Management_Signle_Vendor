<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Models\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('message');

        return $message instanceof Message && Gate::allows('update', $message);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.(int) config('saas.chat.max_message_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.max' => __('Messages may not be longer than :max characters.', [
                'max' => (int) config('saas.chat.max_message_length'),
            ]),
        ];
    }
}
