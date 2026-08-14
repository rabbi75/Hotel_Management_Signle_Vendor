<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Resources;

use App\Modules\Role\Http\Resources\RoleResource;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full administrative view of a user.
 *
 * @mixin User
 */
class UserResource extends JsonResource
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
            'initials' => $this->initials(),
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'bio' => $this->bio,
            'avatar_url' => $this->avatarUrl(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'theme' => $this->theme->value,
            'email_verified' => $this->email_verified_at !== null,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'is_super_admin' => $this->isSuperAdmin(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'suspended_reason' => $this->suspended_reason,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'companies' => $this->whenLoaded('companies', fn (): array => $this->companies
                ->map(static fn ($company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'role' => $company->getRelationValue('pivot')?->role?->value,
                ])
                ->values()
                ->all()),
        ];
    }
}
