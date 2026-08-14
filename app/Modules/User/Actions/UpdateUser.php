<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\DTOs\UserData;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    public function __construct(
        protected CurrentCompany $tenant,
        protected SecurityLogger $security,
    ) {}

    public function handle(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $original = $user->only(['email', 'status']);

            $attributes = $data->toUpdateAttributes();

            // An empty password field means "leave the credential alone"; only a
            // deliberately typed value rotates it.
            if ($data->password === null) {
                unset($attributes['password']);
            }

            $user->fill($attributes)->save();

            $company = $this->tenant->get();

            if ($company !== null && $user->belongsToCompany($company)) {
                $company->members()->updateExistingPivot($user->id, [
                    'role' => $data->companyRole->value,
                    'job_title' => $data->jobTitle,
                ]);
            }

            if ($data->roles !== null) {
                $user->syncRoles($data->roles);
            }

            $user->flushPermissionCache();

            $this->security->log(
                SecurityEvent::UserUpdated,
                auth()->user(),
                __('Updated user :email.', ['email' => $user->email]),
                ['user_id' => $user->id, 'from' => $original, 'roles' => $data->roles ?? []],
            );

            return $user->refresh();
        });
    }
}
