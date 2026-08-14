<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Requests;

use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Rules\SafeWebhookUrl;
use App\Modules\Api\Support\WebhookEventRegistry;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $endpoint = $this->route('endpoint');

        return $user instanceof User && $endpoint instanceof WebhookEndpoint && $user->can('update', $endpoint);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'required', 'string', 'max:2048', 'url:https', new SafeWebhookUrl],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(app(WebhookEventRegistry::class)->names())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
