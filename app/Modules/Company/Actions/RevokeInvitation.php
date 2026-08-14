<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\User\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Withdraws a pending invitation.
 *
 * The row is kept (status `revoked`) rather than deleted so the audit trail
 * still shows that the address was invited and by whom.
 */
class RevokeInvitation
{
    public function __construct(protected SecurityLogger $security) {}

    /**
     * @throws ValidationException
     */
    public function handle(CompanyInvitation $invitation, User $actor): CompanyInvitation
    {
        if ($invitation->status === InvitationStatus::Accepted) {
            throw ValidationException::withMessages([
                'invitation' => __('An accepted invitation cannot be revoked; remove the member instead.'),
            ]);
        }

        $invitation->forceFill(['status' => InvitationStatus::Revoked])->save();

        $this->security->log(
            SecurityEvent::InvitationRevoked,
            $actor,
            __('Revoked the invitation for :email', ['email' => $invitation->email]),
            ['company_id' => $invitation->company_id, 'email' => $invitation->email],
        );

        return $invitation;
    }
}
