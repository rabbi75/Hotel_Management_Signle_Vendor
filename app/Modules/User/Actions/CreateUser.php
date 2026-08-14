<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\DTOs\UserData;
use App\Modules\User\Events\UserCreated;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisions an account on an administrator's behalf.
 *
 * The membership row is written in the same transaction as the user: a user
 * with no `company_user` entry is invisible to every workspace-scoped query and
 * would be unreachable the moment the transaction closed.
 */
class CreateUser
{
    public function __construct(
        protected CurrentCompany $tenant,
        protected SecurityLogger $security,
    ) {}

    public function handle(UserData $data): User
    {
        // An administrator may leave the password blank, in which case the
        // account is invited rather than credentialed and the welcome mail
        // routes the recipient through the password-reset flow.
        $generated = $data->password === null ? Str::password(16) : null;

        return DB::transaction(function () use ($data, $generated): User {
            $user = new User;
            $user->fill([
                ...$data->toAttributes(),
                'password' => $data->password ?? $generated,
            ]);
            $user->save();

            $company = $this->tenant->get();

            if ($company !== null) {
                $company->members()->attach($user->id, [
                    'role' => $data->companyRole->value,
                    'job_title' => $data->jobTitle,
                    'joined_at' => now(),
                ]);
            }

            if ($data->roles !== null) {
                $user->syncRoles($data->roles);
            }

            $user->flushPermissionCache();

            $this->security->log(
                SecurityEvent::UserCreated,
                auth()->user(),
                __('Created user :email.', ['email' => $user->email]),
                ['user_id' => $user->id, 'company_id' => $company?->id, 'roles' => $data->roles ?? []],
            );

            event(new UserCreated($user, $company));

            return $user;
        });
    }
}
