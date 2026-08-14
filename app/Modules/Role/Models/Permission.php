<?php

declare(strict_types=1);

namespace App\Modules\Role\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * A single, atomic capability. The authoritative list lives in
 * config/permissions.php; rows here are a projection of it.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property Collection<int, Role> $roles
 */
class Permission extends SpatiePermission
{
    /**
     * The registry group this permission belongs to — the segment before the
     * first dot, e.g. `users` for `users.create`.
     */
    public function group(): string
    {
        return str($this->name)->before('.')->toString();
    }

    /**
     * The description declared for this permission in the registry.
     */
    public function label(): string
    {
        /** @var array<string, array{permissions?: array<string, string>}> $groups */
        $groups = config('permissions.groups', []);

        return $groups[$this->group()]['permissions'][$this->name]
            ?? str($this->name)->after('.')->headline()->toString();
    }

    /**
     * Is this permission still declared in the registry?
     */
    public function isDeclared(): bool
    {
        return in_array($this->name, self::declared(), true);
    }

    /**
     * Every permission name declared in config/permissions.php.
     *
     * @return list<string>
     */
    public static function declared(): array
    {
        /** @var array<string, array{permissions?: array<string, string>}> $groups */
        $groups = config('permissions.groups', []);

        $names = [];

        foreach ($groups as $group) {
            foreach (array_keys($group['permissions'] ?? []) as $name) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }
}
