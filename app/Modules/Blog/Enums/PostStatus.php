<?php

declare(strict_types=1);

namespace App\Modules\Blog\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * Where a post sits in its lifecycle.
 *
 * `Scheduled` is distinct from `Published` with a future date: the difference
 * is what the author asked for, and collapsing the two would make "publish now"
 * and "publish later" indistinguishable in the audit trail.
 */
enum PostStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Scheduled => 'info',
            self::Published => 'success',
            self::Archived => 'warning',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
