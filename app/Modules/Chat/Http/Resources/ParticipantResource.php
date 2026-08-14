<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConversationParticipant
 */
class ParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ConversationParticipant $participant */
        $participant = $this->resource;

        $user = $participant->relationLoaded('user') ? $participant->user : null;

        return [
            'id' => $participant->id,
            'user_id' => $participant->user_id,
            'name' => $user instanceof User ? $user->name : null,
            'initials' => $user instanceof User ? $user->initials() : null,
            'avatar' => $user instanceof User ? $user->avatarUrl() : null,
            'role' => $participant->role->value,
            'role_label' => $participant->role->label(),
            'muted' => $participant->isMuted(),
            'joined_at' => $participant->joined_at?->toIso8601String(),
            'last_read_at' => $participant->last_read_at?->toIso8601String(),
        ];
    }
}
