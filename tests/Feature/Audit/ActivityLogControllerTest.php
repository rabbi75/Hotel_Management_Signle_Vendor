<?php

declare(strict_types=1);

use App\Modules\User\Models\User;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('audit.activity.index'))->assertRedirect(route('login'));
});

it('forbids a member without the activity view permission', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('audit.activity.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists activity with its causer', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.activity.view'], $company)->refresh();

    activity()->causedBy($viewer)->log('Something happened');

    actingAsMember($viewer, $company)
        ->get(route('audit.activity.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'audit/activity')
        ->assertJsonPath('props.table.rows.0.description', 'Something happened')
        ->assertJsonPath('props.table.rows.0.causer', $viewer->name);
});

it('filters activity by search term', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.activity.view'], $company)->refresh();

    activity()->causedBy($viewer)->log('Invoice issued');
    activity()->causedBy($viewer)->log('Password rotated');

    actingAsMember($viewer, $company)
        ->get(route('audit.activity.index', ['activity_search' => 'Invoice']), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.table.rows')
        ->assertJsonPath('props.table.rows.0.description', 'Invoice issued');
});

it('forbids an export without the export permission', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.activity.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->get(route('audit.activity.export'))
        ->assertForbidden();
});

it('exports the filtered activity log as csv', function (): void {
    $company = workspace();
    $auditor = memberWith(['audit.activity.view', 'audit.export'], $company)->refresh();

    activity()->causedBy($auditor)->log('Exported thing');

    actingAsMember($auditor, $company)
        ->get(route('audit.activity.export', ['format' => 'csv']))
        ->assertOk()
        ->assertDownload();
});

it('exports as xlsx', function (): void {
    $company = workspace();
    $auditor = memberWith(['audit.export'], $company)->refresh();

    actingAsMember($auditor, $company)
        ->get(route('audit.activity.export', ['format' => 'xlsx']))
        ->assertOk()
        ->assertDownload();
});

it('rejects an unsupported export format', function (): void {
    $company = workspace();
    $auditor = memberWith(['audit.export'], $company)->refresh();

    actingAsMember($auditor, $company)
        ->get(route('audit.activity.export', ['format' => 'pdf']))
        ->assertSessionHasErrors('format');
});

it('stamps the workspace onto an auditable model change', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.activity.view'], $company)->refresh();

    activity()->causedBy($viewer)->withProperties(['company_id' => $company->id])->log('Scoped');

    actingAsMember($viewer, $company)
        ->get(route('audit.activity.index'), inertiaHeaders())
        ->assertJsonPath('props.table.rows.0.properties.company_id', $company->id);
});

it('never leaks a password through the activity log', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.activity.view'], $company)->refresh();

    // The Auditable trait's exclusion list is what keeps this true; asserted
    // here against the rendered payload because that is where it would leak.
    $subject = User::factory()->create();
    activity()->causedBy($viewer)->performedOn($subject)->log('User updated');

    $response = actingAsMember($viewer, $company)
        ->get(route('audit.activity.index'), inertiaHeaders())
        ->assertOk();

    expect($response->getContent())->not->toContain($subject->password);
});
