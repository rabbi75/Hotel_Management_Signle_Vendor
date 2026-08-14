<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Full-page renders
|------------------------------------------------------------------------------
|
| Deliberately *not* Inertia XHR visits. An Inertia request returns JSON and
| never touches the root Blade view, so it cannot catch the failure this file
| exists for: `@vite` throwing "Unable to locate file in Vite manifest" when a
| controller renders a page component that does not exist on disk.
|
*/

it('renders every developer screen as a full page', function (string $routeName): void {
    $company = workspace();
    $admin = superAdmin($company);

    actingAsMember($admin, $company)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'tokens' => 'api.tokens.index',
    'webhooks' => 'api.webhooks.index',
    'logs' => 'api.logs.index',
    'docs' => 'api.docs',
]);
