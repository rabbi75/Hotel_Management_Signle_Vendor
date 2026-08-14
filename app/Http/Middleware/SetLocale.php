<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the authenticated user's locale and timezone preferences.
 *
 * Only locales declared in config/saas.php are honoured, so a tampered profile
 * value cannot be used to load an arbitrary translation path.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<string, array{name: string, native: string, dir: string}> $supported */
        $supported = config('saas.locales');

        // Locale is a tenant-user preference; the console operator (a different
        // guard, a different model) has none, so only a User contributes one.
        $user = $request->user();
        $locale = $user instanceof User ? $user->locale : null;

        if (is_string($locale) && array_key_exists($locale, $supported)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
