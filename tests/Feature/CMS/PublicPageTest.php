<?php

declare(strict_types=1);

use App\Modules\CMS\Models\Page;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;

it('renders a published page for a guest', function (): void {
    $company = workspace();
    Page::factory()->forCompany($company)->published()->create(['title' => 'About us', 'slug' => 'about-us']);

    get('/about-us', inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'cms/public-page')
        ->assertJsonPath('props.page.title', 'About us');
});

it('404s an unpublished page for a guest', function (): void {
    $company = workspace();
    Page::factory()->forCompany($company)->create(['slug' => 'secret-draft']);

    get('/secret-draft', inertiaHeaders())->assertNotFound();
});

it('404s a slug that does not exist', function (): void {
    workspace();

    get('/no-such-page', inertiaHeaders())->assertNotFound();
});

it('renders an unpublished page through a valid signed preview link', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create(['title' => 'Draft launch', 'slug' => 'draft-launch']);
    $editor = memberWith(['cms.pages.view'], $company)->refresh();

    $url = URL::temporarySignedRoute('cms.pages.preview', now()->addSeconds(600), ['page' => $page->id]);

    actingAsMember($editor, $company)
        ->get($url, inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'cms/preview')
        ->assertJsonPath('props.page.title', 'Draft launch');
});

it('refuses a preview link whose signature is missing', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $editor = memberWith(['cms.pages.view'], $company)->refresh();

    actingAsMember($editor, $company)
        ->get(route('cms.pages.preview', $page), inertiaHeaders())
        ->assertForbidden();
});

it('refuses a preview link that has expired', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $editor = memberWith(['cms.pages.view'], $company)->refresh();

    $url = URL::temporarySignedRoute('cms.pages.preview', now()->addSeconds(60), ['page' => $page->id]);

    $this->travelTo(now()->addMinutes(5));

    actingAsMember($editor, $company)->get($url, inertiaHeaders())->assertForbidden();
});

it('refuses a preview to someone who cannot view pages', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $outsider = memberWith([], $company)->refresh();

    $url = URL::temporarySignedRoute('cms.pages.preview', now()->addSeconds(600), ['page' => $page->id]);

    actingAsMember($outsider, $company)->get($url, inertiaHeaders())->assertForbidden();
});

it('does not shadow application routes with the catch-all', function (): void {
    $company = workspace();

    // Pages deliberately claiming application paths; the slug rule forbids
    // creating these through the UI, so they are written straight to the table.
    foreach (['dashboard', 'users', 'settings'] as $slug) {
        Page::factory()->forCompany($company)->published()->create([
            'title' => 'Impostor '.$slug,
            'slug' => $slug,
        ]);
    }

    $admin = memberWith(['dashboard.view', 'users.view', 'settings.view'], $company)->refresh();

    actingAsMember($admin, $company)
        ->get('/dashboard', inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'dashboard/index');

    actingAsMember($admin, $company)
        ->get('/users', inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'users/index');

    // `/settings` now redirects to the profile screen rather than rendering a
    // panel of its own — the installation settings it used to list belong to
    // the operator console. Still an application route, so still not the
    // catch-all's to answer.
    actingAsMember($admin, $company)
        ->get('/settings', inertiaHeaders())
        ->assertRedirect('/profile');
});
