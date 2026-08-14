<?php

declare(strict_types=1);

namespace App\Modules\HotelPos\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum PosOrderStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
