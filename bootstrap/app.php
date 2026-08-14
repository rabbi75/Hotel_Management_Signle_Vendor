<?php

declare(strict_types=1);

use App\Http\Middleware\DenyWhileImpersonating;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\SetCurrentCompany;
use App\Http\Middleware\SetCurrentHotel;
use App\Http\Middleware\SetCurrentWorkspace;
use App\Http\Middleware\SetLocale;
use App\Install\Http\Middleware\EnsureInstalled;
use App\Install\Http\Middleware\EnsureInstallerStep;
use App\Install\Http\Middleware\PrepareInstallerRuntime;
use App\Install\Http\Middleware\RedirectIfInstalled;
use App\Modules\Billing\Http\Middleware\EnsurePlanFeature;
use App\Modules\Billing\Http\Middleware\EnsureSubscriptionActive;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/install.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Read by an inline script in the document head to avoid a theme flash;
        // must stay readable to JavaScript, so it is excluded from encryption.
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(prepend: [
            PrepareInstallerRuntime::class,
            EnsureInstalled::class,
        ]);

        $middleware->web(append: [
            SecureHeaders::class,
            HandleAppearance::class,
            SetLocale::class,
            SetCurrentCompany::class,
            SetCurrentWorkspace::class,
            SetCurrentHotel::class,
            // After the tenant is resolved: a workspace whose subscription has
            // lapsed is redirected to billing before any page renders.
            EnsureSubscriptionActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            SecureHeaders::class,
            SetLocale::class,
            SetCurrentCompany::class,
            SetCurrentWorkspace::class,
            SetCurrentHotel::class,
            EnsureInstalled::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'deny.impersonating' => DenyWhileImpersonating::class,
            'plan.feature' => EnsurePlanFeature::class,
            'install.redirect_if_installed' => RedirectIfInstalled::class,
            'install.step' => EnsureInstallerStep::class,
        ]);

        // The console is a separate world with its own login: an unauthenticated
        // hit to /admin/* must land on the admin login, not the tenant one, and
        // an already-signed-in admin bounced off a guest route goes to the
        // console home.
        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('admin', 'admin/*')
                ? route('admin.login')
                : route('login'),
        );

        $middleware->redirectUsersTo(
            fn (Request $request): string => $request->is('admin', 'admin/*')
                ? route('admin.dashboard')
                : '/dashboard',
        );

        // The tenant must be resolved after the session is available but before
        // route-model binding, or bound models would query an unscoped table.
        $middleware->priority([
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            Authenticate::class,
            SetCurrentCompany::class,
            SetCurrentWorkspace::class,
            SetCurrentHotel::class,
            EnsureSubscriptionActive::class,
            ThrottleRequests::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Inertia cannot consume Laravel's Blade error views, so error states
        // are re-rendered as an Inertia page on the client's own layout.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (! $request->header('X-Inertia')) {
                return $response;
            }

            // A stale CSRF token during an Inertia visit should send the user
            // back to retry rather than dead-ending on an error screen.
            if ($response->getStatusCode() === 419) {
                return back()->with('error', __('Your session has expired. Please try again.'));
            }

            if (in_array($response->getStatusCode(), [401, 402, 403, 404, 429, 500, 503], true)) {
                return Inertia::render('errors/error', [
                    'status' => $response->getStatusCode(),
                    'message' => app()->hasDebugModeEnabled() ? $exception->getMessage() : null,
                ])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })
    ->create();
