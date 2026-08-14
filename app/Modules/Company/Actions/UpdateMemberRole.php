<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\DTOs\MemberData;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyMembership;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Changes a member's workspace standing and their RBAC roles.
 *
 * The owner is immovable from here: promoting someone else to owner would
 * leave two owners, and demoting the owner would leave the workspace with
 * none. Both moves belong to {@see TransferOwnership}.
 */
class UpdateMemberRole
{
    public function __construct(
        protected SecurityLogger $security,
        protected NavigationBuilder $navigation,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Company $company, User $member, MemberData $data, User $actor): void
    {
        $this->guard($company, $member, $data->role);

        $previous = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $member->id)
            ->first()?->role;

        DB::transaction(function () use ($company, $member, $data): void {
            $company->members()->updateExistingPivot($member->id, $data->toPivot());

            $assignable = array_values(array_filter(
                $data->roles,
                static fn (string $role): bool => Role::query()
                    ->where('name', $role)
                    ->where('guard_name', 'web')
                    ->exists(),
            ));

            $member->syncRoles($assignable);
        });

        $member->flushPermissionCache();
        $this->navigation->flushFor($member);

        $this->security->log(
            SecurityEvent::MemberRoleChanged,
            $actor,
            __('Changed :name from :from to :to', [
                'name' => $member->name,
                'from' => $previous->value ?? '—',
                'to' => $data->role->value,
            ]),
            [
                'company_id' => $company->id,
                'member_id' => $member->id,
                'from' => $previous?->value,
                'to' => $data->role->value,
                'roles' => $data->roles,
            ],
        );
    }

    /**
     * @throws ValidationException
     */
    protected function guard(Company $company, User $member, CompanyRole $role): void
    {
        if ($company->isOwnedBy($member)) {
            throw ValidationException::withMessages([
                'role' => __('The workspace owner\'s role cannot be changed. Transfer ownership instead.'),
            ]);
        }

        if ($role === CompanyRole::Owner) {
            throw ValidationException::withMessages([
                'role' => __('A workspace can only have one owner. Transfer ownership instead.'),
            ]);
        }

        if (! $member->belongsToCompany($company)) {
            throw ValidationException::withMessages([
                'role' => __('That person is not a member of this workspace.'),
            ]);
        }
    }
}
