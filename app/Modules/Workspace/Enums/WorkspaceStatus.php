<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum WorkspaceStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function isOperational(): bool
    {
        return $this === self::Active;
    }
}
