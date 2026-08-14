<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a workspace and evicts every member from it.
 *
 * Members keep their accounts; only the pointer to this workspace is cleared,
 * so the next request resolves them into whichever workspace they still belong
 * to (or none at all).
 */
class DeleteCompany
{
    public function __construct(
        protected SecurityLogger $security,
        protected NavigationBuilder $navigation,
    ) {}

    public function handle(Company $company, User $actor): void
    {
        DB::transaction(function () use ($company, $actor): void {
            $memberIds = $company->members()->pluck('users.id')->all();

            $this->security->log(
                SecurityEvent::WorkspaceDeleted,
                $actor,
                __('Workspace :name deleted', ['name' => $company->name]),
                ['company_id' => $company->id, 'member_count' => count($memberIds)],
            );

            $company->invitations()->delete();
            $company->members()->detach();

            User::query()
                ->whereIn('id', $memberIds)
                ->update(['current_company_id' => null]);

            $company->delete();
        });

        $this->navigation->flushFor($actor);
        $actor->flushPermissionCache();
    }
}
