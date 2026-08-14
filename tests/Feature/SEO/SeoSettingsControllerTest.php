<?php

declare(strict_types=1);

use App\Support\Settings\SettingsRepository;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('seo.index'))->assertRedirect(route('login'));
});

it('forbids a member without seo.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('seo.index'), inertiaHeaders())
        ->assertForbidden();
});

it('renders the dashboard with the sitemap status and the indexable flag', function (): void {
    $company = workspace();
    $viewer = memberWith(['seo.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->get(route('seo.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'seo/index')
        ->assertJsonPath('props.sitemap.indexable', false)
        ->assertJsonPath('props.defaults.indexable', false);
});

it('forbids saving defaults without seo.update', function (): void {
    $company = workspace();
    $viewer = memberWith(['seo.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->patch(route('seo.settings.update'), [
            'title_max' => 60,
            'description_max' => 160,
            'robots_indexable' => true,
        ])
        ->assertForbidden();
});

it('saves the workspace defaults to the settings store', function (): void {
    $company = workspace();
    $editor = memberWith(['seo.view', 'seo.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->patch(route('seo.settings.update'), [
            'default_title' => 'Acme Journal',
            'default_description' => 'Notes from the Acme engineering team.',
            'title_suffix' => 'Acme',
            'title_max' => 55,
            'description_max' => 150,
            'twitter_handle' => '@acme',
            'robots_indexable' => true,
        ])
        ->assertRedirect();

    $settings = app(SettingsRepository::class);

    expect($settings->getFrom(SettingsRepository::SCOPE_COMPANY, $company->id, 'seo.title_max'))->toBe(55)
        ->and($settings->getFrom(SettingsRepository::SCOPE_COMPANY, $company->id, 'seo.robots_indexable'))->toBeTrue()
        ->and($settings->getFrom(SettingsRepository::SCOPE_COMPANY, $company->id, 'seo.twitter_handle'))->toBe('@acme');
});

it('rejects a malformed twitter handle', function (): void {
    $company = workspace();
    $editor = memberWith(['seo.view', 'seo.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->patch(route('seo.settings.update'), [
            'title_max' => 60,
            'description_max' => 160,
            'robots_indexable' => false,
            'twitter_handle' => 'not a handle',
        ])
        ->assertSessionHasErrors('twitter_handle');
});

it('keeps one workspace out of another workspace defaults', function (): void {
    $company = workspace();
    $other = workspace();

    $editor = memberWith(['seo.view', 'seo.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->patch(route('seo.settings.update'), [
            'title_max' => 44,
            'description_max' => 160,
            'robots_indexable' => true,
        ])
        ->assertRedirect();

    $settings = app(SettingsRepository::class);

    expect($settings->getFrom(SettingsRepository::SCOPE_COMPANY, $other->id, 'seo.title_max'))->toBeNull();
});
