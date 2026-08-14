<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Events\UserRestored;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreUser
{
    public function __construct(protected SecurityLogger $security) {}

    public function handle(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            if ($user->trashed()) {
                $user->restore();
            }

            $user->forceFill([
                'status' => UserStatus::Active,
                'suspended_at' => null,
                'suspended_reason' => null,
            ])->save();

            $this->security->log(
                SecurityEvent::UserRestored,
                auth()->user(),
                __('Restored user :email.', ['email' => $user->email]),
                ['user_id' => $user->id],
            );

            event(new UserRestored($user));

            return $user;
        });
    }
}
