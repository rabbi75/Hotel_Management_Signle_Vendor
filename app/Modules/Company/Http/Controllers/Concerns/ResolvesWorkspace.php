<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers\Concerns;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;

/**
 * Narrows the two nullable values every workspace-scoped controller needs.
 *
 * SetCurrentCompany has already run, so a null here means the user has no
 * workspace at all — an authorisation failure, not a programming error.
 */
trait ResolvesWorkspace
{
    protected function workspace(): Company
    {
        $company = current_company();

        abort_if(! $company instanceof Company, 403, __('You do not belong to a workspace.'));

        return $company;
    }

    protected function actor(Request $request): User
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        return $user;
    }
}
