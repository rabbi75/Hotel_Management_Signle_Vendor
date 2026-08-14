<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Resources;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user, serialised into every Inertia response.
 *
 * Kept deliberately small: it is on the wire for every request, so anything a
 * single page needs belongs in that page's own props instead.
 *
 * @mixin User
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'avatar' => $this->avatarUrl(),
            'avatar_url' => $this->avatarUrl(),
            'initials' => $this->initials(),
            'job_title' => $this->job_title,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'theme' => $this->theme->value,
            'status' => $this->status->value,
            'email_verified' => $this->email_verified_at !== null,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'is_super_admin' => $this->isSuperAdmin(),
        ];
    }
}
