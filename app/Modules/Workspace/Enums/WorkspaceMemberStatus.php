<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Enums;

enum WorkspaceMemberStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
