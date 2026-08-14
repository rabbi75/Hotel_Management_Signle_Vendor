<?php

declare(strict_types=1);

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\Company\Notifications\MemberInvitedNotification;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;

beforeEach(function (): void {
    Notification::fake();
});

it('redirects a guest away from the invitation admin screen', function (): void {
    workspace();

    get(route('companies.invitations.index'))->assertRedirect(route('login'));
});

it('forbids inviting without companies.members.invite', function (): void {
    $company = workspace();
    $member = memberWith(['companies.members.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('companies.invitations.store'), [
            'email' => 'newcomer@example.com',
            'role' => CompanyRole::Member->value,
        ])
        ->assertForbidden();
});

it('sends an invitation', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.invite'], $company)->refresh();

    actingAsMember($admin, $company)
        ->post(route('companies.invitations.store'), [
            'email' => 'Newcomer@Example.com',
            'role' => CompanyRole::Member->value,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('company_invitations', [
        'company_id' => $company->id,
        'email' => 'newcomer@example.com',
        'status' => InvitationStatus::Pending->value,
    ]);

    Notification::assertSentOnDemand(MemberInvitedNotification::class);
});

it('rejects an invalid invitation email', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.invite'], $company)->refresh();

    actingAsMember($admin, $company)
        ->post(route('companies.invitations.store'), ['email' => 'not-an-email', 'role' => CompanyRole::Member->value])
        ->assertSessionHasErrors('email');
});

it('refuses to invite someone who is already a member', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.invite'], $company)->refresh();
    $existing = memberWith([], $company);

    actingAsMember($admin, $company)
        ->post(route('companies.invitations.store'), ['email' => $existing->email, 'role' => CompanyRole::Member->value])
        ->assertSessionHasErrors('email');
});

it('revokes a pending invitation without deleting it', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.invite'], $company)->refresh();

    $invitation = CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'invited_by' => $admin->id,
    ]);

    actingAsMember($admin, $company)
        ->delete(route('companies.invitations.destroy', $invitation->token))
        ->assertRedirect();

    expect($invitation->fresh()?->status)->toBe(InvitationStatus::Revoked);
});

it('accepts an invitation and joins the workspace', function (): void {
    $company = workspace();
    $invitee = User::factory()->create();

    $invitation = CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'invited_by' => $company->owner_id,
        'email' => $invitee->email,
        'role' => CompanyRole::Member,
    ]);

    $this->actingAs($invitee->refresh())
        ->post(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $invitee->id,
        'role' => CompanyRole::Member->value,
    ]);

    expect($invitation->fresh()?->status)->toBe(InvitationStatus::Accepted);
});

it('cannot accept an expired invitation', function (): void {
    $company = workspace();
    $invitee = User::factory()->create();

    $invitation = CompanyInvitation::factory()->expired()->create([
        'company_id' => $company->id,
        'invited_by' => $company->owner_id,
        'email' => $invitee->email,
    ]);

    $this->actingAs($invitee->refresh())
        ->post(route('invitations.accept', $invitation->token))
        ->assertSessionHasErrors('invitation');

    $this->assertDatabaseMissing('company_user', [
        'company_id' => $company->id,
        'user_id' => $invitee->id,
    ]);
});

it('cannot accept a revoked invitation', function (): void {
    $company = workspace();
    $invitee = User::factory()->create();

    $invitation = CompanyInvitation::factory()->revoked()->create([
        'company_id' => $company->id,
        'invited_by' => $company->owner_id,
        'email' => $invitee->email,
    ]);

    $this->actingAs($invitee->refresh())
        ->post(route('invitations.accept', $invitation->token))
        ->assertSessionHasErrors('invitation');
});

it('cannot accept an invitation addressed to someone else', function (): void {
    $company = workspace();
    $invitee = User::factory()->create();

    $invitation = CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'invited_by' => $company->owner_id,
        'email' => 'somebody.else@example.com',
    ]);

    $this->actingAs($invitee->refresh())
        ->post(route('invitations.accept', $invitation->token))
        ->assertSessionHasErrors('invitation');
});

it('sends a guest with no account to registration, keeping the token', function (): void {
    $company = workspace();

    $invitation = CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'invited_by' => $company->owner_id,
        'email' => 'stranger@example.com',
    ]);

    get(route('invitations.show', $invitation->token))
        ->assertRedirectContains('invitation='.$invitation->token)
        ->assertSessionHas('pending_invitation_token', $invitation->token);
});

it('sends a guest who already has an account to login', function (): void {
    $company = workspace();
    $existing = User::factory()->create();

    $invitation = CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'invited_by' => $company->owner_id,
        'email' => $existing->email,
    ]);

    get(route('invitations.show', $invitation->token))
        ->assertRedirect(route('login'))
        ->assertSessionHas('pending_invitation_token', $invitation->token);
});

it('shows the invitation state to the signed-in invitee', function (): void {
    $company = workspace();
    $invitee = User::factory()->create();

    $invitation = CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'invited_by' => $company->owner_id,
        'email' => $invitee->email,
    ]);

    $this->actingAs($invitee->refresh())
        ->get(route('invitations.show', $invitation->token), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'invitations/show')
        ->assertJsonPath('props.state', 'acceptable');
});
