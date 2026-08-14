/*
|------------------------------------------------------------------------------
| Ziggy access that tolerates a missing route
|------------------------------------------------------------------------------
|
| The shell renders links whose routes are registered by feature modules. A kit
| with a module disabled — or a component under test, where the `@routes`
| directive never ran — must degrade to an inert link rather than throwing out
| of a render pass, so every lookup here is guarded.
|
*/

type RouteParams = Parameters<typeof route>[1];

/**
 * Ziggy's zero-argument overload returns its Router, but the ambient `route`
 * declaration resolves to the URL overload here, so the instance is narrowed
 * to the two members this module uses.
 */
interface ZiggyRouter {
    current(): string | undefined;
    has(name: string): boolean;
}

function router(): ZiggyRouter | null {
    if (typeof route !== 'function') {
        return null;
    }

    try {
        return (route as unknown as () => ZiggyRouter)();
    } catch {
        return null;
    }
}

export function hasRoute(name: string | null | undefined): boolean {
    if (!name) {
        return false;
    }

    return router()?.has(name) ?? false;
}

/** The URL for a route name, or `null` when the route is not registered. */
export function routeUrl(name: string | null | undefined, params?: RouteParams): string | null {
    if (!name || !hasRoute(name)) {
        return null;
    }

    try {
        return route(name, params);
    } catch {
        return null;
    }
}

export function currentRouteName(): string | null {
    return router()?.current() ?? null;
}

/** True when the current Ziggy route matches any of the given patterns. */
export function currentRouteMatches(...patterns: string[]): boolean {
    return isNavItemActive(patterns, currentRouteName());
}

/**
 * Matches a Laravel route name against a pattern that may contain `*`
 * wildcards, mirroring `Route::is()` on the server so the sidebar highlights
 * exactly what the backend considers the current route.
 */
export function routeNameMatches(pattern: string, current: string | null): boolean {
    if (!current) {
        return false;
    }

    if (!pattern.includes('*')) {
        return pattern === current;
    }

    const expression = pattern
        .split('*')
        .map((segment) => segment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('.*');

    return new RegExp(`^${expression}$`).test(current);
}

export function isNavItemActive(patterns: string[], current: string | null): boolean {
    return patterns.some((pattern) => routeNameMatches(pattern, current));
}
