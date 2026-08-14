<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum SubscriptionStatus: string
{
    use HasLabel;

    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Incomplete = 'incomplete';
    case Expired = 'expired';

    /**
     * Whether the workspace should still be served. `past_due` is included: the
     * dunning grace period is what decides when access actually stops, and that
     * lives in the renewal command rather than in the status itself.
     */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    /**
     * A subscription that is still running and may therefore be swapped or
     * cancelled. A `canceled` subscription inside its paid period is modelled
     * as `active` with `cancels_at` set, so this stays a pure status question.
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Trialing => 'info',
            self::PastDue => 'warning',
            self::Canceled, self::Expired => 'neutral',
            self::Incomplete => 'destructive',
        };
    }
}
