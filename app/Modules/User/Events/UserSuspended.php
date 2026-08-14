<?php

declare(strict_types=1);

namespace App\Modules\User\Events;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserSuspended
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly ?string $reason = null,
    ) {}
}
