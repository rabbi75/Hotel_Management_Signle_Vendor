<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\DTOs\InvitationData;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\Company\Notifications\MemberInvitedNotification;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues (or re-issues) an invitation to join a workspace.
 *
 * The table carries a unique (company_id, email) pair, so re-inviting someone
 * whose earlier invitation lapsed rewrites that row with a fresh token rather
 * than accumulating dead invitations.
 */
class InviteMember
{
    public function __construct(
        protected SecurityLogger $security,
        protected SubscriptionLimits $limits,
    ) {}

    /**
     * @throws ValidationException
     * @throws BillingException when the plan's seat allowance is reached.
     */
    public function handle(Company $company, InvitationData $data, User $inviter): CompanyInvitation
    {
        $this->guardAgainstExistingMember($company, $data->email);

        $invitation = CompanyInvitation::query()->firstOrNew([
            'company_id' => $company->id,
            'email' => $data->email,
        ]);

        // A brand-new invitation claims a seat; re-issuing an existing pending
        // one already occupies its seat, so only the former is checked.
        if (! $invitation->exists) {
            $this->limits->ensure('seats', 1, $company);
        }

        // forceFill rather than fill: re-issuing has to clear accepted_at, which
        // is deliberately not mass assignable.
        $invitation->forceFill([
            'invited_by' => $inviter->id,
            'role' => $data->role,
            'status' => InvitationStatus::Pending,
            'permission_roles' => $data->permissionRoles === [] ? null : $data->permissionRoles,
            'token' => Str::random(64),
            'expires_at' => now()->addDays((int) config('saas.workspace.invitation_expires_days')),
            'accepted_at' => null,
        ])->save();

        $this->send($invitation, $company);

        $this->security->log(
            SecurityEvent::InvitationSent,
            $inviter,
            __('Invited :email to :company', ['email' => $data->email, 'company' => $company->name]),
            ['company_id' => $company->id, 'email' => $data->email, 'role' => $data->role->value],
        );

        return $invitation;
    }

    /**
     * Delivers to the account behind the address when one exists, so the
     * invitation also lands in their notification bell.
     */
    public function send(CompanyInvitation $invitation, Company $company): void
    {
        $existing = User::query()->where('email', $invitation->email)->first();

        $notification = new MemberInvitedNotification($invitation, $company);

        if ($existing instanceof User) {
            $existing->notify($notification);

            return;
        }

        Notification::route('mail', $invitation->email)->notify($notification);
    }

    /**
     * @throws ValidationException
     */
    protected function guardAgainstExistingMember(Company $company, string $email): void
    {
        $alreadyMember = $company->members()->where('users.email', $email)->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => __('That person is already a member of this workspace.'),
            ]);
        }
    }
}
