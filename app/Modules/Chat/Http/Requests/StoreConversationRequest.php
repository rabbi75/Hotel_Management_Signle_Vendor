<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Enums\ConversationType;
use App\Modules\Chat\Models\Conversation;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Conversation::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $direct = $this->input('type') === ConversationType::Direct->value;

        return [
            'type' => ['required', Rule::enum(ConversationType::class)],
            'name' => [Rule::requiredIf(! $direct), 'nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'participant_ids' => ['required', 'array', $direct ? 'size:1' : 'min:1'],

            // Membership of the active workspace is the ceiling on who can be
            // added: the pivot is the authority, not the users table.
            'participant_ids.*' => [
                'integer',
                Rule::exists('company_user', 'user_id')->where('company_id', current_company_id()),
            ],
        ];
    }

    public function conversationType(): ConversationType
    {
        return ConversationType::from((string) $this->string('type'));
    }

    /**
     * @return list<int>
     */
    public function participantIds(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = (array) $this->input('participant_ids', []);

        return array_values(array_unique(array_map(intval(...), $ids)));
    }
}
