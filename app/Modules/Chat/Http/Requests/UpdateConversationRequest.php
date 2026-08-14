<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation && Gate::allows('update', $conversation);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'participant_ids' => ['sometimes', 'array'],
            'participant_ids.*' => [
                'integer',
                Rule::exists('company_user', 'user_id')->where('company_id', current_company_id()),
            ],
        ];
    }
}
