<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Enums\MessageType;
use App\Modules\Chat\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation && Gate::allows('post', $conversation);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $conversation = $this->route('conversation');

        return [
            'body' => ['required', 'string', 'max:'.(int) config('saas.chat.max_message_length')],

            // `system` is authored by the application only; a client that asks
            // for it would be forging an announcement.
            'type' => ['nullable', Rule::in([MessageType::Text->value, MessageType::Attachment->value])],

            'reply_to_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')
                    ->where('conversation_id', $conversation instanceof Conversation ? $conversation->id : 0)
                    ->whereNull('deleted_at'),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.(int) config('saas.chat.max_attachment_kb')],
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

    /**
     * @return list<UploadedFile>
     */
    public function attachments(): array
    {
        $files = $this->file('attachments');

        return is_array($files) ? array_values($files) : [];
    }
}
