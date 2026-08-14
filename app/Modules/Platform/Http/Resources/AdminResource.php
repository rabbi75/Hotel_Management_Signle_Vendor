<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Admin
 */
class AdminResource extends JsonResource
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
            'email' => $this->email,
            'initials' => $this->initials(),
            'avatar' => $this->avatarUrl(),
            'status' => $this->status,
            'is_super_admin' => $this->isSuperAdmin(),
            'roles' => $this->getRoleNames()->values()->all(),
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
