<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Contracts\SocialProvider;
use App\Modules\Auth\DTOs\SocialUser;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The default {@see SocialProvider} driver, backed by laravel/socialite.
 *
 * Socialite is not a dependency of the starter kit, so the driver exists but
 * refuses to run until it is installed. That keeps the contract, the registry
 * and the routes present and testable without forcing an OAuth dependency on
 * deployments that never use one.
 */
class SocialiteProvider implements SocialProvider
{
    /**
     * Referenced as a string rather than ::class so static analysis of a
     * project without socialite installed stays clean.
     */
    protected const SOCIALITE = 'Laravel\\Socialite\\Facades\\Socialite';

    public function __construct(
        protected string $provider,
        protected string $label,
        protected string $icon,
    ) {}

    public function key(): string
    {
        return $this->provider;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function isConfigured(): bool
    {
        return (bool) config("services.{$this->provider}.client_id")
            && (bool) config("services.{$this->provider}.client_secret");
    }

    public function redirect(): RedirectResponse
    {
        $response = $this->driver()->redirect();

        if (! $response instanceof RedirectResponse) {
            throw new RuntimeException("The {$this->provider} driver did not return a redirect.");
        }

        return $response;
    }

    public function user(): SocialUser
    {
        $user = $this->driver()->user();

        $id = $this->string($user->getId());

        if ($id === null) {
            throw new RuntimeException("The {$this->provider} driver returned an identity with no id.");
        }

        return new SocialUser(
            id: $id,
            email: $this->string($user->getEmail()),
            name: $this->string($user->getName()) ?? $this->string($user->getNickname()),
            avatar: $this->string($user->getAvatar()),
            token: $this->string($user->token ?? null),
            refreshToken: $this->string($user->refreshToken ?? null),
        );
    }

    /**
     * @throws RuntimeException when laravel/socialite is not installed.
     */
    protected function driver(): mixed
    {
        if (! class_exists(self::SOCIALITE)) {
            throw new RuntimeException(
                'Social authentication requires laravel/socialite. Run: composer require laravel/socialite'
            );
        }

        return call_user_func([self::SOCIALITE, 'driver'], $this->provider);
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
