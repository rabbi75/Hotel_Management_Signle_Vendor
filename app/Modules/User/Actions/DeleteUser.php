<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Events\UserDeleted;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  bool  $permanent  Purge the record instead of soft deleting it.
     */
    public function handle(User $user, bool $permanent = false): void
    {
        DB::transaction(function () use ($user, $permanent): void {
            $context = ['user_id' => $user->id, 'email' => $user->email];

            // Sessions and tokens survive a soft delete, so they are revoked
            // explicitly rather than relying on the row disappearing.
            $user->tokens()->delete();

            if ($permanent) {
                $user->companies()->detach();
                $user->syncRoles([]);
                $user->forceDelete();
            } else {
                $user->delete();
            }

            $this->security->log(
                $permanent ? SecurityEvent::UserForceDeleted : SecurityEvent::UserDeleted,
                auth()->user(),
                $permanent
                    ? __('Permanently deleted user :email.', ['email' => $context['email']])
                    : __('Deleted user :email.', ['email' => $context['email']]),
                $context,
            );

            event(new UserDeleted($user, $permanent));
        });
    }
}
