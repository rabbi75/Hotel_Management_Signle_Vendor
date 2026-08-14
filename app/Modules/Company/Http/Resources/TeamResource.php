<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Resources;

use App\Modules\Company\Models\Team;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Team $team */
        $team = $this->resource;

        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'color' => $team->color,
            'description' => $team->description,
            'department_id' => $team->department_id,
            'department' => $team->relationLoaded('department') ? $team->department?->name : null,
            'lead_id' => $team->lead_id,
            'lead' => $team->relationLoaded('lead') ? $team->lead?->name : null,
            'members_count' => $this->counter($team, 'members_count'),
            'members' => $team->relationLoaded('members')
                ? $team->members->map(static fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'initials' => $member->initials(),
                ])->values()->all()
                : [],
            'created_at' => $team->created_at?->toIso8601String(),
        ];
    }

    protected function counter(Team $team, string $key): ?int
    {
        $value = $team->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
