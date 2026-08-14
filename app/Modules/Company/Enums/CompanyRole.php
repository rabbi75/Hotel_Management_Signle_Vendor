<?php

declare(strict_types=1);

namespace App\Modules\Company\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * A member's standing inside one workspace.
 *
 * Distinct from the RBAC roles in spatie/laravel-permission: this describes
 * ownership of the workspace itself (who may bill, rename, or delete it),
 * while permissions describe what a member may do to the data inside it.
 */
enum CompanyRole: string
{
    use HasLabel;

    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';

    public function color(): string
    {
        return match ($this) {
            self::Owner => 'primary',
            self::Admin => 'info',
            self::Member => 'neutral',
            self::Guest => 'warning',
        };
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageWorkspace(): bool
    {
        return $this === self::Owner;
    }
}
