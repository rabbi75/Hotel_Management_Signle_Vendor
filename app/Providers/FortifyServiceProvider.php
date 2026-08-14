<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Auth\Actions\CreateNewUser;
use App\Modules\Auth\Actions\ResetUserPassword;
use App\Modules\Auth\Actions\UpdateUserPassword;
use App\Modules\User\Actions\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerActions();
        $this->registerViews();
        $this->registerRateLimiters();
    }

    protected function registerActions(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
    }

    /**
     * Fortify renders Blade by default; every screen is an Inertia page here.
     */
    protected function registerViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => true,
            'canRegister' => (bool) config('saas.auth.registration_enabled'),
            'status' => $request->session()->get('status'),
            'socials' => $this->enabledSocialProviders(),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'socials' => $this->enabledSocialProviders(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute((int) config('saas.auth.max_login_attempts'))->by($key);
        });

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->session()->get('login.id')));

        RateLimiter::for('passkeys', function (Request $request): Limit {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                (is_string($credentialId) && $credentialId !== '' ? $credentialId : $request->session()->getId()).'|'.$request->ip()
            );
        });
    }

    /**
     * Only advertise a social provider once its credentials are actually
     * configured, so the login screen never offers a button that would fail.
     *
     * @return list<string>
     */
    protected function enabledSocialProviders(): array
    {
        /** @var list<string> $configured */
        $configured = config('saas.auth.socials', []);

        return array_values(array_filter(
            $configured,
            static fn (string $provider): bool => (bool) config("services.{$provider}.client_id"),
        ));
    }
}
