<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Company\Actions\InviteMember;
use App\Modules\Company\Actions\RevokeInvitation;
use App\Modules\Company\DTOs\InvitationData;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Company\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Modules\Company\Http\Requests\StoreInvitationRequest;
use App\Modules\Company\Http\Resources\InvitationResource;
use App\Modules\Company\Models\CompanyInvitation;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class InvitationController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): Response
    {
        $company = $this->workspace();

        Gate::authorize('viewAny', CompanyInvitation::class);

        $query = CompanyInvitation::query()
            ->where('company_id', $company->id)
            ->with('inviter');

        $table = TableBuilder::for($query, $request, 'invitations')
            ->columns([
                Column::make('email', __('Email'))->sortable()->searchable()->locked(),
                Column::make('role', __('Role'))->sortable(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('expires_at', __('Expires'))->sortable(),
                Column::make('created_at', __('Sent'))->sortable(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(InvitationStatus::class)->multiple(),
                Filter::make('role', __('Role'))->fromEnum(CompanyRole::class)->multiple(),
            ])
            ->defaultSort('created_at', 'desc')
            ->transform(fn (CompanyInvitation $invitation): array => (new InvitationResource($invitation))->resolve($request));

        return Inertia::render('companies/invitations/index', [
            'table' => $table->toArray(),
            'roles' => array_values(array_map(
                static fn (CompanyRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                array_filter(CompanyRole::cases(), static fn (CompanyRole $role): bool => $role !== CompanyRole::Owner),
            )),
            'permission_roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all(),
            'can' => [
                'invite' => Gate::allows('create', CompanyInvitation::class),
            ],
        ]);
    }

    public function store(StoreInvitationRequest $request, InviteMember $inviteMember): RedirectResponse
    {
        $company = $this->workspace();

        try {
            $invitation = $inviteMember->handle($company, InvitationData::fromRequest($request), $this->actor($request));
        } catch (BillingException $exception) {
            // The plan's seat allowance is full. Send them to the plan picker
            // rather than dead-ending on the invite form.
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Invitation sent to :email.', ['email' => $invitation->email]));
    }

    public function resend(CompanyInvitation $invitation, InviteMember $inviteMember): RedirectResponse
    {
        Gate::authorize('resend', $invitation);

        abort_unless($invitation->isAcceptable(), 422, __('Only a pending invitation can be resent.'));

        $inviteMember->send($invitation, $this->workspace());

        return back()->with('success', __('Invitation resent to :email.', ['email' => $invitation->email]));
    }

    public function destroy(Request $request, CompanyInvitation $invitation, RevokeInvitation $revokeInvitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        $revokeInvitation->handle($invitation, $this->actor($request));

        return back()->with('success', __('Invitation revoked.'));
    }
}
