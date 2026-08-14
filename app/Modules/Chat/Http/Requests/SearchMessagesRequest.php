<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SearchMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('chat.access');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'conversation_id' => ['nullable', 'integer'],
            'cursor' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function term(): string
    {
        return trim((string) $this->string('q'));
    }
}
