<?php

declare(strict_types=1);

namespace App\Modules\Role\Http\Resources;

use App\Modules\Role\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Permission
 */
class PermissionResource extends JsonResource
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
            'group' => $this->group(),
            'guard_name' => $this->guard_name,
            'declared' => $this->isDeclared(),
        ];
    }
}
