<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Resources;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person's membership of the active workspace.
 *
 * Membership columns are read from query aliases (`membership_*`) rather than
 * from a pivot relation, so the same resource serves the joined table query and
 * a plain user record.
 *
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $role = $this->membershipRole($user);

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'job_title' => $this->attribute($user, 'membership_job_title') ?? $user->job_title,
            'status' => $user->status->value,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'role_color' => $role?->color(),
            'department_id' => $this->intAttribute($user, 'membership_department_id'),
            'department' => $this->attribute($user, 'membership_department_name'),
            'joined_at' => $this->attribute($user, 'membership_joined_at'),
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->pluck('name')->values()->all()
                : [],
        ];
    }

    protected function membershipRole(User $user): ?CompanyRole
    {
        $raw = $this->attribute($user, 'membership_role');

        return $raw === null ? null : CompanyRole::tryFrom($raw);
    }

    /**
     * Read a query alias without tripping strict mode's missing-attribute check.
     */
    protected function attribute(User $user, string $key): ?string
    {
        $value = $user->getAttributes()[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    protected function intAttribute(User $user, string $key): ?int
    {
        $value = $this->attribute($user, $key);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
