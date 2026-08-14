import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

/**
 * Plan feature checks against the entitlements the server shares each response.
 *
 * Like usePermissions, this gates what is *rendered* and is never a boundary:
 * every plan-gated route is independently enforced by the `plan.feature`
 * middleware on the server.
 */
export function useEntitlements() {
    const { auth } = usePage<SharedProps>().props;

    const granted = useMemo(() => new Set(auth.entitlements), [auth.entitlements]);

    const hasFeature = useCallback((feature: string): boolean => granted.has(feature), [granted]);

    return { hasFeature } as const;
}
