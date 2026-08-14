<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Requests;

use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Rules\SafeWebhookUrl;
use App\Modules\Api\Support\WebhookEventRegistry;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', WebhookEndpoint::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', 'url:https', new SafeWebhookUrl],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(app(WebhookEventRegistry::class)->names())],
            'is_active' => ['boolean'],
        ];
    }
}
