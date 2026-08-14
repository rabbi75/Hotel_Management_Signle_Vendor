<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Resources;

use App\Modules\Company\Models\CompanyInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyInvitation
 */
class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CompanyInvitation $invitation */
        $invitation = $this->resource;

        return [
            'id' => $invitation->id,
            // The route key for resend/revoke. Exposing it is safe here: this
            // resource is only ever serialised to holders of
            // `companies.members.view` on the owning workspace, who are exactly
            // the people entitled to re-send the invitation anyway.
            'token' => $invitation->token,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'role_label' => $invitation->role->label(),
            'status' => $invitation->status->value,
            'status_label' => $invitation->status->label(),
            'status_color' => $invitation->status->color(),
            'permission_roles' => $invitation->permission_roles ?? [],
            'invited_by' => $invitation->relationLoaded('inviter') ? $invitation->inviter?->name : null,
            'company' => $invitation->relationLoaded('company') ? $invitation->company->name : null,
            'is_expired' => $invitation->isExpired(),
            'is_acceptable' => $invitation->isAcceptable(),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'accepted_at' => $invitation->accepted_at?->toIso8601String(),
            'created_at' => $invitation->created_at?->toIso8601String(),
        ];
    }
}
