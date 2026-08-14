<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the sidebar tree for a user, filtered by their permissions.
 *
 * Modules contribute their own sections at boot via {@see register()}, so the
 * navigation is never a hard-coded list that drifts from the routes that exist.
 * The result is cached per user because it is serialised into every response.
 */
class NavigationBuilder
{
    /** @var list<NavigationSection> */
    protected array $sections = [];

    public function register(NavigationSection $section): void
    {
        $this->sections[] = $section;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function for(User $user): array
    {
        return Cache::remember(
            $this->cacheKey($user),
            (int) config('saas.cache.navigation_ttl'),
            fn (): array => $this->build($user),
        );
    }

    public function flushFor(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    /**
     * Flush every member's navigation for one workspace.
     *
     * A plan change is workspace-wide, and the nav tree is feature-gated, so
     * every member's cached tree for this workspace is now potentially stale —
     * not only the person who triggered the change. Keyed per member and per
     * company, so it never touches their other workspaces.
     */
    public function flushForCompany(Company $company): void
    {
        $locale = app()->getLocale();

        $company->members()->pluck('users.id')->each(function (int $userId) use ($company, $locale): void {
            Cache::forget("navigation:{$userId}:{$company->id}:{$locale}");
        });
    }

    /**
     * The nav now depends on the active workspace's plan (feature-gated items),
     * so the key includes the company: the same user sees a different tree in a
     * workspace on a different plan, and switching workspaces must not serve a
     * stale one. Any subscription change flushes this via {@see flushFor()}.
     */
    protected function cacheKey(User $user): string
    {
        $companyId = current_company_id() ?? 'none';

        return "navigation:{$user->id}:{$companyId}:".app()->getLocale();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function build(User $user): array
    {
        $sections = array_map(
            static fn (NavigationSection $section): array => $section->toArray($user),
            $this->merged(),
        );

        // Drop sections whose every item was filtered out by permissions, so an
        // empty group heading never renders.
        return array_values(array_filter(
            $sections,
            static fn (array $section): bool => $section['items'] !== [],
        ));
    }

    /**
     * Collapse sections registered under the same label into one.
     *
     * Modules register independently, so more than one can claim a heading. The
     * input is already ordered, so the first occurrence of a label fixes the
     * merged section's position.
     *
     * @return list<NavigationSection>
     */
    protected function merged(): array
    {
        $merged = [];

        foreach ($this->sorted() as $section) {
            $merged[$section->label] = isset($merged[$section->label])
                ? $merged[$section->label]->merge($section)
                : $section;
        }

        return array_values($merged);
    }

    /**
     * @return list<NavigationSection>
     */
    protected function sorted(): array
    {
        $sections = $this->sections;

        usort($sections, static fn (NavigationSection $a, NavigationSection $b): int => $a->order <=> $b->order);

        return $sections;
    }
}
