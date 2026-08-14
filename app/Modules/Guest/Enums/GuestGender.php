<?php

declare(strict_types=1);

namespace App\Modules\Guest\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum GuestGender: string
{
    use HasLabel;

    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case PreferNotToSay = 'prefer_not_to_say';
}
