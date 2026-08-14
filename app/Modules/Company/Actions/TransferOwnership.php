<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Notifications\OwnershipTransferredNotification;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Hands a workspace to another member.
 *
 * The outgoing owner is demoted to admin rather than removed: losing ownership
 * should never silently lock someone out of a workspace they still work in.
 */
class TransferOwnership
{
    public function __construct(
        protected SecurityLogger $security,
        protected NavigationBuilder $navigation,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Company $company, User $newOwner, User $currentOwner): Company
    {
        $this->guard($company, $newOwner, $currentOwner);

        DB::transaction(function () use ($company, $newOwner, $currentOwner): void {
            $company->members()->updateExistingPivot($newOwner->id, ['role' => CompanyRole::Owner->value]);
            $company->members()->updateExistingPivot($currentOwner->id, ['role' => CompanyRole::Admin->value]);

            $company->forceFill(['owner_id' => $newOwner->id])->save();

            // The seeded admin role is what actually carries workspace rights;
            // it is granted only if the installation seeded it.
            if (Role::query()->where('name', 'admin')->where('guard_name', 'web')->exists()) {
                $newOwner->assignRole('admin');
            }
        });

        foreach ([$newOwner, $currentOwner] as $party) {
            $party->flushPermissionCache();
            $this->navigation->flushFor($party);
            $party->notify(new OwnershipTransferredNotification($company, $currentOwner, $newOwner));
        }

        $this->security->log(
            SecurityEvent::WorkspaceOwnershipTransferred,
            $currentOwner,
            __('Transferred :company to :name', ['company' => $company->name, 'name' => $newOwner->name]),
            [
                'company_id' => $company->id,
                'from_user_id' => $currentOwner->id,
                'to_user_id' => $newOwner->id,
            ],
        );

        return $company;
    }

    /**
     * @throws ValidationException
     */
    protected function guard(Company $company, User $newOwner, User $currentOwner): void
    {
        if (! $company->isOwnedBy($currentOwner)) {
            throw ValidationException::withMessages([
                'user_id' => __('Only the workspace owner may transfer ownership.'),
            ]);
        }

        if ($newOwner->id === $currentOwner->id) {
            throw ValidationException::withMessages([
                'user_id' => __('You already own this workspace.'),
            ]);
        }

        if (! $newOwner->belongsToCompany($company)) {
            throw ValidationException::withMessages([
                'user_id' => __('Ownership can only be transferred to an existing member.'),
            ]);
        }

        if ($newOwner->isSuspended()) {
            throw ValidationException::withMessages([
                'user_id' => __('A suspended account cannot own a workspace.'),
            ]);
        }
    }
}
