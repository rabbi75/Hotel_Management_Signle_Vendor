<?php

declare(strict_types=1);

namespace App\Modules\Company\Policies;

use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\User\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('companies.members.view');
    }

    public function create(User $user): bool
    {
        return $user->can('companies.members.invite');
    }

    public function resend(User $user, CompanyInvitation $invitation): bool
    {
        return $invitation->company_id === current_company_id() && $user->can('companies.members.invite');
    }

    /**
     * Whoever may invite may also withdraw, and the original sender may always
     * withdraw their own invitation.
     */
    public function revoke(User $user, CompanyInvitation $invitation): bool
    {
        if ($invitation->company_id !== current_company_id()) {
            return false;
        }

        return $invitation->invited_by === $user->id || $user->can('companies.members.invite');
    }
}
