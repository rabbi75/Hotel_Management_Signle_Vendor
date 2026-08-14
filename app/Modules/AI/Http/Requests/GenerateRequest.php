<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Services\ProviderManager;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', AiGeneration::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required_without:template_id', 'nullable', 'string', 'max:100000'],
            'system' => ['nullable', 'string', 'max:20000'],
            'provider' => ['nullable', 'string', Rule::in(app(ProviderManager::class)->keys())],
            'model' => ['nullable', 'string', 'max:120'],
            'max_tokens' => ['nullable', 'integer', 'between:1,128000'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('ai_prompt_templates', 'id')->where('company_id', current_company_id())->whereNull('deleted_at'),
            ],
            'variables' => ['nullable', 'array'],
        ];
    }
}
