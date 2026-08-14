import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

/**
 * Permission checks against the list the server shares on every response.
 *
 * These gate what is *rendered*; they are a convenience, never a security
 * boundary. Every route they lead to must be independently authorised on the
 * server by a Policy or `permission:` middleware.
 */
export function usePermissions() {
    const { auth } = usePage<SharedProps>().props;

    const granted = useMemo(() => new Set(auth.permissions), [auth.permissions]);
    const isSuperAdmin = auth.user?.is_super_admin ?? false;

    const can = useCallback((permission: string): boolean => isSuperAdmin || granted.has(permission), [granted, isSuperAdmin]);

    const canAny = useCallback(
        (...permissions: string[]): boolean => isSuperAdmin || permissions.some((permission) => granted.has(permission)),
        [granted, isSuperAdmin],
    );

    const canAll = useCallback(
        (...permissions: string[]): boolean => isSuperAdmin || permissions.every((permission) => granted.has(permission)),
        [granted, isSuperAdmin],
    );

    return { can, canAny, canAll, isSuperAdmin } as const;
}
