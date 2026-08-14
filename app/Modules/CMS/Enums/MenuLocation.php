<?php

declare(strict_types=1);

namespace App\Modules\CMS\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum MenuLocation: string
{
    use HasLabel;

    case Header = 'header';
    case Footer = 'footer';
    case Sidebar = 'sidebar';
}
