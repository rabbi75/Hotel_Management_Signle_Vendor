<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Resources;

use App\Modules\Api\Models\ApiRequestLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApiRequestLog
 */
class ApiRequestLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ApiRequestLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'method' => $log->method,
            'path' => $log->path,
            'route_name' => $log->route_name,
            'status' => $log->status,
            'is_error' => $log->isError(),
            'duration_ms' => $log->duration_ms,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'user' => $log->relationLoaded('user') ? $log->user?->name : null,
            'token' => $log->relationLoaded('token') ? $log->token?->name : null,
            'api_token_id' => $log->api_token_id,
            'request_body' => $log->request_body,
            'response_body' => $log->response_body,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
