<?php

declare(strict_types=1);

namespace App\Modules\Role\Http\Resources;

use App\Modules\Role\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label(),
            'description' => $this->description(),
            'guard_name' => $this->guard_name,
            'is_system' => $this->isSystem(),
            'is_super_admin' => $this->isSuperAdmin(),
            'users_count' => $this->whenCounted('users'),
            'permissions_count' => $this->whenCounted('permissions'),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
