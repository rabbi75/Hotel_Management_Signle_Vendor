<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Invoice;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('billing.invoices.index'))->assertRedirect(route('login'));
});

it('forbids a member without billing.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('billing.invoices.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only invoices belonging to the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $mine = Invoice::factory()->forCompany($company)->create(['number' => 'INV-MINE']);
    $theirs = Invoice::factory()->forCompany($other)->create(['number' => 'INV-THEIRS']);

    $viewer = memberWith(['billing.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('billing.invoices.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'billing/invoices');

    $numbers = collect($response->json('props.table.rows'))->pluck('number')->all();

    expect($numbers)->toContain($mine->number)
        ->and($numbers)->not->toContain($theirs->number);
});

it('renders a printable invoice', function (): void {
    $company = workspace();
    $viewer = memberWith(['billing.view'], $company)->refresh();
    $invoice = Invoice::factory()->forCompany($company)->create(['total' => 4200]);

    actingAsMember($viewer, $company)
        ->get(route('billing.invoices.show', $invoice))
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee('$42.00');
});

it('will not serve an invoice from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $foreign = Invoice::factory()->forCompany($other)->create();
    $viewer = memberWith(['billing.view', 'billing.invoices.download'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->get(route('billing.invoices.show', $foreign))
        ->assertNotFound();
});

it('requires the download permission to download', function (): void {
    $company = workspace();
    $viewer = memberWith(['billing.view'], $company)->refresh();
    $invoice = Invoice::factory()->forCompany($company)->create();

    actingAsMember($viewer, $company)
        ->get(route('billing.invoices.download', $invoice))
        ->assertForbidden();
});

it('sends the invoice as an attachment when permitted', function (): void {
    $company = workspace();
    $viewer = memberWith(['billing.view', 'billing.invoices.download'], $company)->refresh();
    $invoice = Invoice::factory()->forCompany($company)->create();

    actingAsMember($viewer, $company)
        ->get(route('billing.invoices.download', $invoice))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$invoice->number.'.html"');
});
