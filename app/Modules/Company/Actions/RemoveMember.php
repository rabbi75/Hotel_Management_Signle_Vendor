<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Team;
use App\Modules\Company\Notifications\RemovedFromWorkspaceNotification;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Detaches a member from a workspace.
 *
 * The two failure modes callers actually hit — removing the owner, and removing
 * yourself — are surfaced as validation errors so the UI can render them next
 * to the control the user pressed rather than as a 403 page.
 */
class RemoveMember
{
    public function __construct(
        protected SecurityLogger $security,
        protected NavigationBuilder $navigation,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Company $company, User $member, User $actor): void
    {
        $this->guard($company, $member, $actor);

        DB::transaction(function () use ($company, $member): void {
            $teamIds = Team::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->pluck('id')
                ->all();

            $member->teams()->detach($teamIds);
            $company->members()->detach($member->id);

            if ($member->current_company_id === $company->id) {
                $member->forceFill(['current_company_id' => null])->save();
            }
        });

        $member->flushPermissionCache();
        $this->navigation->flushFor($member);

        $member->notify(new RemovedFromWorkspaceNotification($company));

        $this->security->log(
            SecurityEvent::MemberRemoved,
            $actor,
            __('Removed :name from :company', ['name' => $member->name, 'company' => $company->name]),
            ['company_id' => $company->id, 'member_id' => $member->id],
        );
    }

    /**
     * @throws ValidationException
     */
    protected function guard(Company $company, User $member, User $actor): void
    {
        if (! $member->belongsToCompany($company)) {
            throw ValidationException::withMessages([
                'user' => __('That person is not a member of this workspace.'),
            ]);
        }

        if ($company->isOwnedBy($member)) {
            throw ValidationException::withMessages([
                'user' => __('The workspace owner cannot be removed. Transfer ownership first.'),
            ]);
        }

        if ($member->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot remove yourself from a workspace.'),
            ]);
        }
    }
}
