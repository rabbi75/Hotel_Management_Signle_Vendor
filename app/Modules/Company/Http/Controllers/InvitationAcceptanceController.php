<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Actions\AcceptInvitation;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Company\Http\Resources\InvitationResource;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The invitee's side of an invitation.
 *
 * Reachable by anyone holding the token, including people with no account yet,
 * so every branch is handled as a rendered state rather than an exception: the
 * recipient of a lapsed invitation should be told what happened, not shown a
 * 404.
 */
class InvitationAcceptanceController extends Controller
{
    /**
     * Session key holding a token across the registration detour.
     */
    public const SESSION_KEY = 'pending_invitation_token';

    public function __construct(protected NavigationBuilder $navigation) {}

    public function show(Request $request, CompanyInvitation $invitation): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->sendToAuthentication($request, $invitation);
        }

        return Inertia::render('invitations/show', [
            'invitation' => (new InvitationResource($invitation->load('company', 'inviter')))->resolve($request),
            'state' => $this->state($invitation, $user),
            'token' => $invitation->token,
        ]);
    }

    public function store(Request $request, CompanyInvitation $invitation, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $company = $acceptInvitation->handle($invitation, $user);

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put((string) config('saas.workspace.session_key'), $company->id);

        return redirect()
            ->route('dashboard')
            ->with('success', __('Welcome to :company.', ['company' => $company->name]));
    }

    /**
     * A guest is routed to registration or login depending on whether the
     * invited address already has an account, with the token preserved either
     * way so the flow can resume afterwards.
     */
    protected function sendToAuthentication(Request $request, CompanyInvitation $invitation): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY, $invitation->token);

        $hasAccount = User::query()->where('email', $invitation->email)->exists();

        if ($hasAccount || ! Route::has('register')) {
            return redirect()->guest(route('login'))
                ->with('info', __('Sign in as :email to accept your invitation.', ['email' => $invitation->email]));
        }

        return redirect()
            ->route('register', ['invitation' => $invitation->token, 'email' => $invitation->email])
            ->with('info', __('Create your account to join :company.', [
                'company' => $this->companyName($invitation),
            ]));
    }

    /**
     * Why this invitation can or cannot be accepted right now.
     */
    protected function state(CompanyInvitation $invitation, User $user): string
    {
        return match (true) {
            $invitation->status === InvitationStatus::Accepted => 'accepted',
            $invitation->status === InvitationStatus::Revoked => 'revoked',
            $invitation->isExpired() => 'expired',
            mb_strtolower($invitation->email) !== mb_strtolower($user->email) => 'email_mismatch',
            default => 'acceptable',
        };
    }

    protected function companyName(CompanyInvitation $invitation): string
    {
        $company = $invitation->company()->first();

        return $company instanceof Company ? $company->name : '';
    }
}
