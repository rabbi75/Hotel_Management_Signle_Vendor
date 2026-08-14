<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;

it('lets a tenant open and view a support ticket', function (): void {
    $company = workspace();
    $user = memberWith(['support.view', 'support.create'], $company);

    actingAsMember($user, $company)
        ->post(route('support.store'), [
            'subject' => 'Cannot assign a plan',
            'body' => 'Billing page returns 500 when saving a card.',
            'priority' => 'high',
            'category' => 'billing',
        ])
        ->assertRedirect();

    $ticket = SupportTicket::query()->where('company_id', $company->id)->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::AwaitingSupport)
        ->and($ticket->messages()->count())->toBe(1)
        ->and(SecurityLog::query()->where('event', SecurityEvent::SupportTicketCreated)->exists())->toBeTrue();

    actingAsMember($user, $company)
        ->get(route('support.show', $ticket), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.ticket.number', $ticket->number)
        ->assertJsonPath('props.ticket.messages.0.body', 'Billing page returns 500 when saving a card.');
});

it('hides another company ticket from a tenant', function (): void {
    $companyA = workspace();
    $userA = memberWith(['support.view', 'support.create'], $companyA);

    actingAsMember($userA, $companyA)->post(route('support.store'), [
        'subject' => 'Private ticket',
        'body' => 'Should stay in company A.',
        'priority' => 'normal',
        'category' => 'other',
    ]);

    $ticket = SupportTicket::query()->where('company_id', $companyA->id)->firstOrFail();

    $companyB = workspace();
    $userB = memberWith(['support.view'], $companyB);

    actingAsMember($userB, $companyB)
        ->get(route('support.show', $ticket))
        ->assertNotFound();
});

it('forbids ticket access without support permissions', function (): void {
    $company = workspace();
    $user = memberWith(['dashboard.view'], $company);

    actingAsMember($user, $company)
        ->get(route('support.index'))
        ->assertForbidden();
});

it('lets an operator reply assign and resolve tickets including internal notes', function (): void {
    $company = workspace();
    $user = memberWith(['support.view', 'support.create'], $company);

    actingAsMember($user, $company)->post(route('support.store'), [
        'subject' => 'Need help',
        'body' => 'First message',
        'priority' => 'normal',
        'category' => 'technical',
    ]);

    $ticket = SupportTicket::query()->where('company_id', $company->id)->firstOrFail();
    $admin = platformAdminWith(['platform.tickets.view', 'platform.tickets.manage']);

    actingAsAdmin($admin)
        ->get(route('admin.tickets.index'), inertiaHeaders())
        ->assertOk();

    actingAsAdmin($admin)
        ->post(route('admin.tickets.reply', $ticket), [
            'body' => 'Looking into this.',
            'is_internal' => false,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingCustomer)
        ->and($ticket->fresh()->assigned_admin_id)->toBe($admin->id);

    actingAsAdmin($admin)
        ->post(route('admin.tickets.reply', $ticket), [
            'body' => 'Internal investigation note',
            'is_internal' => true,
        ])
        ->assertRedirect();

    actingAsAdmin($admin)
        ->patch(route('admin.tickets.update', $ticket), [
            'status' => TicketStatus::Resolved->value,
            'assigned_admin_id' => $admin->id,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);

    actingAsMember($user, $company)
        ->get(route('support.show', $ticket), inertiaHeaders())
        ->assertOk()
        ->assertJsonMissing(['Internal investigation note']);

    expect(
        SupportTicketMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('is_internal', true)
            ->exists(),
    )->toBeTrue();
});

it('lets the tenant close a resolved ticket', function (): void {
    $company = workspace();
    $user = memberWith(['support.view', 'support.create'], $company);

    actingAsMember($user, $company)->post(route('support.store'), [
        'subject' => 'Close me',
        'body' => 'Done soon',
        'priority' => 'low',
        'category' => 'account',
    ]);

    $ticket = SupportTicket::query()->where('company_id', $company->id)->firstOrFail();
    $ticket->forceFill(['status' => TicketStatus::Resolved, 'resolved_at' => now()])->save();

    actingAsMember($user, $company)
        ->post(route('support.close', $ticket))
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
});

it('forbids the admin ticket inbox without permission', function (): void {
    $admin = platformAdminWith(['platform.tenants.view']);

    actingAsAdmin($admin)
        ->get(route('admin.tickets.index'))
        ->assertForbidden();
});
