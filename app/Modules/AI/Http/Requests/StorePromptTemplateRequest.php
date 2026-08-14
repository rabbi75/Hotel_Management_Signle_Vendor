<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', AiPromptTemplate::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
            'prompt' => ['required', 'string', 'max:100000'],
            'variables' => ['array'],
            'variables.*.name' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/', 'max:60'],
            'variables.*.label' => ['required', 'string', 'max:120'],
            'variables.*.type' => ['required', 'string', Rule::in(['text', 'textarea', 'number', 'select'])],
            'variables.*.required' => ['boolean'],
            'provider' => ['nullable', 'string', Rule::in(app(ProviderManager::class)->keys())],
            'model' => ['nullable', 'string', 'max:120'],
            'is_shared' => ['boolean'],
        ];
    }
}
