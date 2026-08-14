<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Actions\TransferOwnership;
use App\Modules\Company\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Modules\Company\Http\Requests\TransferOwnershipRequest;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hands the active workspace to another member.
 *
 * Sits behind `password.confirm` because it is the one action that can take a
 * workspace away from the person performing it.
 */
class OwnershipTransferController extends Controller
{
    use ResolvesWorkspace;

    public function create(Request $request): Response
    {
        $company = $this->workspace();

        Gate::authorize('transferOwnership', $company);

        $candidates = $company->members()
            ->where('users.id', '!=', $company->owner_id)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);

        return Inertia::render('companies/transfer-ownership', [
            'company' => ['uuid' => $company->uuid, 'name' => $company->name],
            'candidates' => $candidates->map(static fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ])->values()->all(),
        ]);
    }

    public function store(TransferOwnershipRequest $request, TransferOwnership $transferOwnership): RedirectResponse
    {
        $company = $this->workspace();

        /** @var User $newOwner */
        $newOwner = User::query()->findOrFail($request->integer('user_id'));

        $transferOwnership->handle($company, $newOwner, $this->actor($request));

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', __('Ownership transferred to :name.', ['name' => $newOwner->name]));
    }
}
