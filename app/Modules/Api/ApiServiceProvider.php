<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Http\Middleware\SetCurrentCompany;
use App\Http\Middleware\SetCurrentHotel;
use App\Modules\Api\Console\PruneApiLogs;
use App\Modules\Api\Http\Middleware\LogsApiRequests;
use App\Modules\Api\Http\Middleware\RendersProblemResponses;
use App\Modules\Api\Http\Middleware\ResolveTokenCompany;
use App\Modules\Api\Models\ApiRequestLog;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Policies\ApiRequestLogPolicy;
use App\Modules\Api\Policies\ApiTokenPolicy;
use App\Modules\Api\Policies\WebhookEndpointPolicy;
use App\Modules\Api\Support\WebhookEventRegistry;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

class ApiServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        ApiToken::class => ApiTokenPolicy::class,
        WebhookEndpoint::class => WebhookEndpointPolicy::class,
        ApiRequestLog::class => ApiRequestLogPolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(WebhookEventRegistry::class);
    }

    protected function bootModule(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);

        $this->registerPublicApiRoutes();
        $this->registerCoreEvents();
        $this->registerNavigation();

        if ($this->app->runningInConsole()) {
            $this->commands([PruneApiLogs::class]);
        }
    }

    /**
     * The versioned public API. Registered here rather than through the module
     * convention so the prefix comes from config and the middleware stack is
     * explicit: authenticate, bind the tenant from the token, throttle, log.
     */
    protected function registerPublicApiRoutes(): void
    {
        $file = $this->path('Routes/public-api.php');

        if (! is_file($file)) {
            return;
        }

        Route::prefix((string) config('saas.api.prefix'))
            ->as('api.v1.')
            ->middleware([
                'api',
                RendersProblemResponses::class,
                ResolveTokenCompany::class,
                'throttle:api',
                LogsApiRequests::class,
            ])
            // The session-based tenant resolver has nothing to resolve here and
            // would fatal on a session-less request; the token carries the
            // workspace instead.
            ->withoutMiddleware([SetCurrentCompany::class, SetCurrentHotel::class])
            ->group($file);
    }

    /**
     * Events this kit publishes out of the box. Other modules add their own by
     * resolving the registry in their provider.
     */
    protected function registerCoreEvents(): void
    {
        $this->app->make(WebhookEventRegistry::class)
            ->registerMany('Users', [
                'user.created' => __('A user joined the workspace.'),
                'user.updated' => __('A user profile changed.'),
                'user.deleted' => __('A user was removed.'),
            ])
            ->registerMany('Workspace', [
                'workspace.updated' => __('Workspace settings changed.'),
                'workspace.member_invited' => __('A member was invited.'),
                'workspace.member_removed' => __('A member was removed.'),
            ])
            ->registerMany('API', [
                'api.token_created' => __('An API token was issued.'),
                'api.token_revoked' => __('An API token was revoked.'),
            ]);
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Developer', 80)->items([
                NavigationItem::make('API tokens', 'api.tokens.index')
                    ->icon('key-round')
                    ->permissions('api.tokens.view')
                    ->activeWhen('api.tokens.*')
                    ->order(10),

                NavigationItem::make('Webhooks', 'api.webhooks.index')
                    ->icon('webhook')
                    ->permissions('api.webhooks.manage')
                    ->activeWhen('api.webhooks.*')
                    ->order(20),

                NavigationItem::make('API logs', 'api.logs.index')
                    ->icon('scroll-text')
                    ->permissions('api.logs.view')
                    ->activeWhen('api.logs.*')
                    ->order(30),

                NavigationItem::make('API docs', 'api.docs')
                    ->icon('book-open')
                    ->permissions('api.tokens.view')
                    ->activeWhen('api.docs')
                    ->order(40),
            ]),
        );
    }
}
