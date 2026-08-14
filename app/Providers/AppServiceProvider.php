<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Navigation\NavigationBuilder;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\CurrentWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class);
        $this->app->singleton(CurrentWorkspace::class);
        $this->app->singleton(NavigationBuilder::class);
        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(): void
    {
        $this->configureFactories();
        $this->configureModels();
        $this->configureDates();
        $this->configurePasswords();
        $this->configureRateLimiting();
        $this->configureUrls();
    }

    /**
     * Teach Eloquent where module factories live.
     *
     * The default resolver maps App\Models\Foo to Database\Factories\FooFactory.
     * Models here are namespaced App\Modules\{Module}\Models\Foo, which the
     * default would resolve to Database\Factories\Modules\{Module}\Models\…, so
     * the module segment is stripped and every factory kept in one place.
     */
    protected function configureFactories(): void
    {
        Factory::guessFactoryNamesUsing(static function (string $model): string {
            $name = str_contains($model, 'App\\Modules\\')
                ? Str::afterLast($model, '\\Models\\')
                : Str::after($model, 'App\\Models\\');

            return 'Database\\Factories\\'.$name.'Factory';
        });
    }

    protected function configureModels(): void
    {
        // Strict mode turns N+1 queries and typo'd attributes into exceptions
        // during development rather than silent degradation in production.
        Model::shouldBeStrict(! $this->app->environment('production'));
    }

    protected function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    protected function configurePasswords(): void
    {
        Password::defaults(fn (): Password => $this->app->environment('production')
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8)->letters()->numbers());
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute((int) config('saas.rate_limits.api'))
            ->by((string) ($request->user()?->id ?? $request->ip())));

        // Keyed by credential as well as IP so one attacker cannot lock every
        // account behind a shared NAT, and one account cannot be brute forced
        // from a rotating address pool.
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute((int) config('saas.rate_limits.auth'))
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute((int) config('saas.rate_limits.search'))
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('export', fn (Request $request): Limit => Limit::perMinute((int) config('saas.rate_limits.export'))
            ->by((string) ($request->user()?->id ?? $request->ip())));
    }

    protected function configureUrls(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
