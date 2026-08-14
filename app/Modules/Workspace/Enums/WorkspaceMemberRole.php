<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum WorkspaceMemberRole: string
{
    use HasLabel;

    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';
}
