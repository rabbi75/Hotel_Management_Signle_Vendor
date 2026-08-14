<?php

declare(strict_types=1);

namespace App\Modules\Blog\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum CommentStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Spam = 'spam';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Spam => 'danger',
        };
    }
}
