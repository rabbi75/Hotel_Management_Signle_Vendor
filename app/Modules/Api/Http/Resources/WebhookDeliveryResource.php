<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Resources;

use App\Modules\Api\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebhookDelivery
 */
class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WebhookDelivery $delivery */
        $delivery = $this->resource;

        return [
            'id' => $delivery->id,
            'endpoint_id' => $delivery->webhook_endpoint_id,
            'endpoint_url' => $delivery->relationLoaded('endpoint') ? $delivery->endpoint?->url : null,
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'attempt' => $delivery->attempt,
            'status' => $delivery->status->value,
            'status_label' => $delivery->status->label(),
            'status_color' => $delivery->status->color(),
            'status_code' => $delivery->status_code,
            'response_body' => $delivery->response_body,
            'error' => $delivery->error,
            'duration_ms' => $delivery->duration_ms,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }
}
