<?php

declare(strict_types=1);

namespace App\Modules\Auth\Contracts;

use App\Modules\Auth\DTOs\SocialUser;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * One OAuth identity provider.
 *
 * The interface is deliberately narrow — a redirect out and a user coming back —
 * so a provider can be backed by Socialite, by a hand-rolled client, or by a
 * fake in tests without the rest of the module knowing which.
 */
interface SocialProvider
{
    /**
     * The provider key as used in config and routes, e.g. `google`.
     */
    public function key(): string;

    public function label(): string;

    public function icon(): string;

    /**
     * Whether this provider has usable credentials configured.
     */
    public function isConfigured(): bool;

    /**
     * Send the user to the provider's consent screen.
     */
    public function redirect(): RedirectResponse;

    /**
     * Resolve the returning user from the callback request.
     */
    public function user(): SocialUser;
}
