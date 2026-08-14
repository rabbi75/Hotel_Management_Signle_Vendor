<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $template = $this->route('template');

        return $user instanceof User && $template instanceof AiPromptTemplate && $user->can('update', $template);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'nullable', 'string', 'max:60'],
            'prompt' => ['sometimes', 'required', 'string', 'max:100000'],
            'variables' => ['sometimes', 'array'],
            'variables.*.name' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/', 'max:60'],
            'variables.*.label' => ['required', 'string', 'max:120'],
            'variables.*.type' => ['required', 'string', Rule::in(['text', 'textarea', 'number', 'select'])],
            'variables.*.required' => ['boolean'],
            'provider' => ['sometimes', 'nullable', 'string', Rule::in(app(ProviderManager::class)->keys())],
            'model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_shared' => ['sometimes', 'boolean'],
        ];
    }
}
