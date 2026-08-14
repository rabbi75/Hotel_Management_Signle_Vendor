<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Actions;

use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Modules\Workspace\DTOs\WorkspaceData;
use App\Modules\Workspace\Enums\WorkspaceMemberRole;
use App\Modules\Workspace\Enums\WorkspaceMemberStatus;
use App\Modules\Workspace\Enums\WorkspaceStatus;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    public function __construct(protected SubscriptionLimits $limits) {}

    public function handle(Company $company, WorkspaceData $data, ?User $creator = null): Workspace
    {
        $this->limits->ensure('workspaces', 1, $company);

        return DB::transaction(function () use ($company, $data, $creator): Workspace {
            $workspace = new Workspace([
                ...$data->toAttributes(),
                'slug' => CreateDefaultWorkspace::uniqueSlug($company, $data->name),
                'status' => WorkspaceStatus::Active,
                'is_default' => false,
            ]);
            $workspace->company_id = $company->id;
            $workspace->save();

            if ($creator instanceof User) {
                $workspace->members()->attach($creator->id, [
                    'role' => WorkspaceMemberRole::Admin->value,
                    'status' => WorkspaceMemberStatus::Active->value,
                    'joined_at' => now(),
                ]);
            }

            return $workspace;
        });
    }
}
