<?php

declare(strict_types=1);

namespace App\Modules\Role\Models;

use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * An RBAC role.
 *
 * Subclassed from the package model purely so relations, scopes and helpers can
 * be typed for static analysis; the storage and behaviour are unchanged.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property Collection<int, Permission> $permissions
 * @property Collection<int, User> $users
 * @property int|null $users_count
 * @property int|null $permissions_count
 */
class Role extends SpatieRole
{
    /**
     * Roles declared in config/permissions.php are structural: the application
     * assigns them by name, so they must not be renamed or removed through the
     * UI even by a user who holds `roles.update`.
     */
    public function isSystem(): bool
    {
        /** @var array<string, array<string, mixed>> $declared */
        $declared = config('permissions.roles', []);

        return array_key_exists($this->name, $declared);
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === config('permissions.super_admin_role', 'super-admin');
    }

    /**
     * The human label declared for this role, falling back to its name.
     */
    public function label(): string
    {
        /** @var array<string, array{label?: string}> $declared */
        $declared = config('permissions.roles', []);

        return $declared[$this->name]['label'] ?? str($this->name)->headline()->toString();
    }

    public function description(): ?string
    {
        /** @var array<string, array{description?: string}> $declared */
        $declared = config('permissions.roles', []);

        return $declared[$this->name]['description'] ?? null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAssignable(Builder $query): void
    {
        $query->where('name', '!=', config('permissions.super_admin_role', 'super-admin'));
    }
}
