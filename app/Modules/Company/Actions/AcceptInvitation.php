<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\Company\Notifications\InvitationAcceptedNotification;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Turns a pending invitation into a membership.
 *
 * Every precondition is re-checked here rather than trusted from the
 * controller: the same action is reachable from the web flow and from the
 * post-registration redirect.
 */
class AcceptInvitation
{
    public function __construct(
        protected SecurityLogger $security,
        protected NavigationBuilder $navigation,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(CompanyInvitation $invitation, User $user): Company
    {
        $this->guard($invitation, $user);

        /** @var Company $company */
        $company = Company::query()->findOrFail($invitation->company_id);

        DB::transaction(function () use ($invitation, $user, $company): void {
            $company->members()->syncWithoutDetaching([
                $user->id => [
                    'role' => $invitation->role->value,
                    'joined_at' => now(),
                ],
            ]);

            foreach ($invitation->permission_roles ?? [] as $role) {
                if (Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
                    $user->assignRole($role);
                }
            }

            $invitation->forceFill([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $user->forceFill(['current_company_id' => $company->id])->save();
        });

        $user->flushPermissionCache();
        $this->navigation->flushFor($user);

        $inviter = $invitation->inviter()->first();

        if ($inviter instanceof User) {
            $inviter->notify(new InvitationAcceptedNotification($company, $user));
        }

        $this->security->log(
            SecurityEvent::InvitationAccepted,
            $user,
            __(':name joined :company', ['name' => $user->name, 'company' => $company->name]),
            ['company_id' => $company->id, 'invitation_id' => $invitation->id],
        );

        return $company;
    }

    /**
     * @throws ValidationException
     */
    protected function guard(CompanyInvitation $invitation, User $user): void
    {
        if ($invitation->status === InvitationStatus::Accepted) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation has already been accepted.'),
            ]);
        }

        if ($invitation->status === InvitationStatus::Revoked) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation has been revoked.'),
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation has expired.'),
            ]);
        }

        if (mb_strtolower($invitation->email) !== mb_strtolower($user->email)) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation was sent to a different email address.'),
            ]);
        }
    }
}
