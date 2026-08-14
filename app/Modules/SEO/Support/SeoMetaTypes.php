<?php

declare(strict_types=1);

namespace App\Modules\SEO\Support;

use App\Modules\SEO\Contracts\Seoable;
use Illuminate\Database\Eloquent\Model;

/**
 * The allow list of subjects whose metadata may be edited over HTTP.
 *
 * Keyed by the short type the client sends. Anything not listed here cannot be
 * addressed at all, which is what stops `type` from becoming a way to name an
 * arbitrary class. Classes are resolved by name and guarded, so a kit shipped
 * without the blog — or with a CMS this module has never heard of — still boots.
 */
final class SeoMetaTypes
{
    /** @var array<string, string> */
    private const CANDIDATES = [
        'post' => 'App\Modules\Blog\Models\Post',
        'page' => 'App\Modules\CMS\Models\Page',
    ];

    /**
     * @return array<string, class-string>
     */
    public static function allowed(): array
    {
        $types = [];

        foreach (self::CANDIDATES as $key => $class) {
            // The Seoable check doubles as the "is this really one of ours"
            // check: the trait that satisfies it can only be used on a Model.
            if (class_exists($class) && is_subclass_of($class, Seoable::class)) {
                $types[$key] = $class;
            }
        }

        return $types;
    }

    /**
     * The subject, or null when the type is unknown or the row is outside the
     * caller's workspace — the global tenant scope answers the second half.
     */
    public static function resolve(string $type, int $id): ?Model
    {
        $class = self::allowed()[$type] ?? null;

        if ($class === null) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $class;

        $model = $instance->newQuery()->whereKey($id)->first();

        return $model instanceof Model && $model instanceof Seoable ? $model : null;
    }

    /**
     * A human label for the type, used in the SEO dashboard listing.
     */
    public static function label(string $type): string
    {
        return match ($type) {
            'post' => __('Blog post'),
            'page' => __('Page'),
            default => str($type)->headline()->toString(),
        };
    }
}
