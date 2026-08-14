<?php

declare(strict_types=1);

namespace App\Modules\AI\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum GenerationStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Streaming = 'streaming';
    case Completed = 'completed';
    case Refused = 'refused';
    case Truncated = 'truncated';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Failed => 'destructive',
            self::Refused, self::Truncated => 'warning',
            self::Pending, self::Streaming => 'muted',
        };
    }

    public function isBillable(): bool
    {
        return in_array($this, [self::Completed, self::Truncated], true);
    }
}
