<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Auth\Contracts\SocialProvider;
use App\Modules\Auth\DTOs\SocialUser;
use App\Modules\Auth\Models\SocialAccount;
use App\Modules\Auth\Services\SocialProviderRegistry;
use App\Modules\Company\Actions\CreateCompany;
use App\Modules\Company\DTOs\CompanyData;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * OAuth sign-in and account linking.
 *
 * The rule that matters here is the last one: an OAuth identity may never be
 * attached to an address that already belongs to a different local account.
 * Doing so would let anyone who can obtain a token for an address take over the
 * account behind it.
 */
class SocialAuthController extends Controller
{
    public function __construct(
        protected SocialProviderRegistry $registry,
        protected SecurityLogger $security,
        protected CreateCompany $createCompany,
    ) {}

    public function redirect(string $provider): SymfonyRedirectResponse
    {
        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->driver($provider);
        $identity = $driver->user();

        if ($identity->email === null) {
            return $this->failure(
                (string) __(':provider did not share an email address, so an account cannot be created.', [
                    'provider' => $driver->label(),
                ]),
            );
        }

        $existingLink = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $identity->id)
            ->first();

        if ($existingLink instanceof SocialAccount) {
            $user = $existingLink->user()->first();

            if (! $user instanceof User) {
                abort(403, __('The linked account no longer exists.'));
            }

            $this->refresh($existingLink, $identity);

            return $this->signIn($request, $user, $provider);
        }

        $byEmail = User::query()->where('email', $identity->email)->first();
        $signedIn = $request->user();

        if ($signedIn instanceof User && $byEmail instanceof User && $byEmail->id !== $signedIn->id) {
            return $this->failure((string) __('That :provider account is already associated with another user.', [
                'provider' => $driver->label(),
            ]));
        }

        $user = $signedIn instanceof User ? $signedIn : $byEmail;

        if ($user instanceof User && mb_strtolower($user->email) !== mb_strtolower($identity->email)) {
            return $this->failure((string) __('That :provider account uses a different email address.', [
                'provider' => $driver->label(),
            ]));
        }

        $user ??= $this->register($identity);

        $this->link($user, $provider, $identity);

        return $this->signIn($request, $user, $provider);
    }

    /**
     * The OAuth callback has no useful referer, so failures land on whichever
     * screen the user could have started from.
     */
    protected function failure(string $message): RedirectResponse
    {
        $route = Auth::check() ? 'dashboard' : 'login';

        return redirect()->route($route)->with('error', $message);
    }

    /**
     * Resolve a configured driver, or 404 for an unknown / unconfigured one.
     */
    protected function driver(string $provider): SocialProvider
    {
        $driver = $this->registry->get($provider);

        abort_if($driver === null || ! $driver->isConfigured(), 404);

        return $driver;
    }

    protected function register(SocialUser $identity): User
    {
        abort_unless((bool) config('saas.auth.registration_enabled'), 403, __('Registration is currently closed.'));

        return DB::transaction(function () use ($identity): User {
            $name = $identity->name ?? Str::before((string) $identity->email, '@');

            $user = User::create([
                'name' => $name,
                'first_name' => Str::before($name, ' '),
                'last_name' => Str::after($name, ' ') === $name ? null : Str::afterLast($name, ' '),
                'email' => $identity->email,
                // No local password: the account is reachable only through the
                // provider until the user sets one via password reset.
                'password' => Str::password(32),
                'status' => UserStatus::Active,
                'timezone' => config('saas.defaults.timezone'),
                'locale' => config('saas.defaults.locale'),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $company = $this->createCompany->handle(
                CompanyData::forRegistration(__(":name's workspace", ['name' => $name]), $user),
                $user,
            );

            $user->refresh();

            if ($user->current_company_id === null) {
                $user->forceFill(['current_company_id' => $company->id])->save();
            }

            return $user;
        });
    }

    protected function link(User $user, string $provider, SocialUser $identity): SocialAccount
    {
        $account = SocialAccount::query()->updateOrCreate(
            ['provider' => $provider, 'provider_id' => $identity->id],
            [
                'user_id' => $user->id,
                'email' => $identity->email,
                'nickname' => $identity->name,
                'avatar' => $identity->avatar,
                'token' => $identity->token,
                'refresh_token' => $identity->refreshToken,
                'expires_at' => $identity->expiresIn === null ? null : now()->addSeconds($identity->expiresIn),
            ],
        );

        $this->security->log(
            SecurityEvent::SocialAccountLinked,
            $user,
            __('Linked a :provider account', ['provider' => $provider]),
            ['provider' => $provider],
        );

        return $account;
    }

    protected function refresh(SocialAccount $account, SocialUser $identity): void
    {
        $account->forceFill([
            'email' => $identity->email,
            'avatar' => $identity->avatar,
            'token' => $identity->token,
            'refresh_token' => $identity->refreshToken,
            'expires_at' => $identity->expiresIn === null ? null : now()->addSeconds($identity->expiresIn),
        ])->save();
    }

    protected function signIn(Request $request, User $user, string $provider): RedirectResponse
    {
        abort_if($user->isSuspended(), 403, __('Your account has been suspended.'));

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()
            ->intended(route('dashboard'))
            ->with('success', __('Signed in with :provider.', ['provider' => $provider]));
    }
}
