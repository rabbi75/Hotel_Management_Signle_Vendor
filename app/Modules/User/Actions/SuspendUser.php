<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Events\UserSuspended;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class SuspendUser
{
    public function __construct(protected SecurityLogger $security) {}

    public function handle(User $user, ?string $reason = null): User
    {
        return DB::transaction(function () use ($user, $reason): User {
            $user->forceFill([
                'status' => UserStatus::Suspended,
                'suspended_at' => now(),
                'suspended_reason' => $reason,
            ])->save();

            // A suspension that leaves live API tokens behind is not a
            // suspension; the credential outlives the account state.
            $user->tokens()->delete();

            $this->security->log(
                SecurityEvent::UserSuspended,
                auth()->user(),
                __('Suspended user :email.', ['email' => $user->email]),
                ['user_id' => $user->id, 'reason' => $reason],
            );

            event(new UserSuspended($user, $reason));

            return $user;
        });
    }
}
