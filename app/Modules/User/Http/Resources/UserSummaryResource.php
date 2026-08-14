<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Resources;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The cheap shape used wherever a user is merely referenced — table cells,
 * assignee pickers, activity rows.
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
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
            'avatar_url' => $this->avatarUrl(),
            'status' => $this->status->value,
        ];
    }
}
