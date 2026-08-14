<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Services;

use App\Modules\Company\Models\Company;
use App\Modules\Notification\Notifications\BaseNotification;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Collection;

class HotelStaffNotifier
{
    /**
     * Notify every workspace member who holds a permission, excluding the actor.
     *
     * @param  list<string>  $permissions
     */
    public function notifyPermissionHolders(
        BaseNotification $notification,
        array $permissions,
        ?int $companyId = null,
        ?User $except = null,
    ): void {
        $company = $this->resolveCompany($companyId);

        if (! $company instanceof Company) {
            return;
        }

        $this->recipients($company, $permissions, $except)
            ->each(static fn (User $user): mixed => $user->notify($notification));
    }

    public function notifyUser(?User $user, BaseNotification $notification): void
    {
        if ($user instanceof User) {
            $user->notify($notification);
        }
    }

    /**
     * @param  list<string>  $permissions
     * @return Collection<int, User>
     */
    protected function recipients(Company $company, array $permissions, ?User $except): Collection
    {
        return $company->members()
            ->orderBy('users.name')
            ->get()
            ->filter(function (User $member) use ($permissions, $except): bool {
                if ($except instanceof User && $member->is($except)) {
                    return false;
                }

                foreach ($permissions as $permission) {
                    if ($member->can($permission)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    protected function resolveCompany(?int $companyId): ?Company
    {
        if ($companyId !== null) {
            return Company::query()->find($companyId);
        }

        return app(CurrentCompany::class)->get();
    }
}
