<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Resources;

use App\Modules\Api\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApiToken
 */
class ApiTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ApiToken $token */
        $token = $this->resource;

        return [
            'id' => $token->id,
            'name' => $token->name,
            // The plaintext is never persisted; this is a prefix of the hash,
            // enough to tell two tokens apart and useless to an attacker.
            'fingerprint' => $token->fingerprint(),
            'abilities' => array_map(strval(...), $token->abilities ?? []),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'is_expired' => $token->isExpired(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }
}
