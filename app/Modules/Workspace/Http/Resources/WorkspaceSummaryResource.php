<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Resources;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Workspace;
use App\Modules\Workspace\Models\WorkspaceMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workspace
 */
class WorkspaceSummaryResource extends JsonResource
{
    /**
     * @return array{id: int, uuid: string, name: string, slug: string, logo: string|null, initials: string, role: string|null, is_default: bool}
     */
    public function toArray(Request $request): array
    {
        /** @var Workspace $workspace */
        $workspace = $this->resource;

        $user = $request->user();

        return [
            'id' => $workspace->id,
            'uuid' => $workspace->uuid,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'logo' => $workspace->logoUrl(),
            'initials' => $workspace->initials(),
            'role' => $user instanceof User ? $this->role($workspace, $user) : null,
            'is_default' => $workspace->is_default,
        ];
    }

    protected function role(Workspace $workspace, User $user): ?string
    {
        if ($workspace->relationLoaded('pivot')) {
            $pivot = $workspace->getRelation('pivot');

            if ($pivot instanceof WorkspaceMembership) {
                return $pivot->role->value;
            }
        }

        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        return $membership?->role->value;
    }
}
