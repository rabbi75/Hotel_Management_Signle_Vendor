<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Full-page renders
|------------------------------------------------------------------------------
|
| Full document GETs, not Inertia XHR visits: only a real page render resolves
| the component through the Vite manifest, which is where a missing page file
| surfaces as a 500 rather than a blank screen.
|
*/

it('renders every AI screen as a full page', function (string $routeName): void {
    $company = workspace();
    $admin = superAdmin($company);

    actingAsMember($admin, $company)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'playground' => 'ai.index',
    'templates' => 'ai.templates.index',
    'history' => 'ai.history.index',
    'credits' => 'ai.credits.index',
    'providers' => 'ai.providers.index',
]);
