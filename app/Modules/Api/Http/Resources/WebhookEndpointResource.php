<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Resources;

use App\Modules\Api\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebhookEndpoint
 */
class WebhookEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WebhookEndpoint $endpoint */
        $endpoint = $this->resource;

        return [
            'id' => $endpoint->id,
            'url' => $endpoint->url,
            'description' => $endpoint->description,
            'events' => $endpoint->events,
            // Never the secret itself: possession of it is enough to forge a
            // delivery. The UI only needs to know one exists.
            'secret_hint' => 'whsec_'.str_repeat('•', 12),
            'is_active' => $endpoint->is_active,
            'failure_count' => $endpoint->failure_count,
            'last_success_at' => $endpoint->last_success_at?->toIso8601String(),
            'last_failure_at' => $endpoint->last_failure_at?->toIso8601String(),
            'disabled_at' => $endpoint->disabled_at?->toIso8601String(),
            'created_at' => $endpoint->created_at?->toIso8601String(),
        ];
    }
}
