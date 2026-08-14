<?php

declare(strict_types=1);

namespace App\Modules\CMS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Rejects a slug the application already routes.
 *
 * The public catch-all is registered last precisely so it cannot shadow
 * /dashboard or /settings — but a page claiming one of those slugs would then
 * be permanently unreachable, which is a worse failure than refusing to save
 * it. The check happens here, on write, rather than at request time.
 */
class NotReservedSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $slug = Str::of($value)->trim('/')->lower()->before('/')->toString();

        /** @var list<string> $reserved */
        $reserved = array_map(strval(...), (array) config('saas.cms.reserved_slugs', []));

        if (in_array($slug, $reserved, true)) {
            $fail(__('The slug ":slug" is reserved by the application.', ['slug' => $slug]));
        }
    }
}
