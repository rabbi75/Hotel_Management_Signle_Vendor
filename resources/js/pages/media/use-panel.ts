import { usePermissions } from '@/hooks/use-permissions';
import { AdminLayout } from '@/layouts/admin-layout';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

export type Panel = 'app' | 'admin';

export function useMediaPanel() {
    const { auth, ...props } = usePage<SharedProps & { panel?: Panel }>().props;
    const panel: Panel = props.panel === 'admin' ? 'admin' : 'app';
    const { can } = usePermissions();
    const isOperator = panel === 'admin';
    const isSuperOperator = Boolean(auth.admin?.is_super_admin);
    const prefix = isOperator ? 'admin.media.' : 'media.';

    const r = useCallback(
        (name: string, params?: Parameters<typeof route>[1]): string => route(`${prefix}${name}`, params),
        [prefix],
    );

    const may = useCallback(
        (permission: string): boolean => (isOperator ? isSuperOperator || can('platform.content.manage') : can(permission)),
        [can, isOperator, isSuperOperator],
    );

    return useMemo(
        () => ({
            panel,
            r,
            may,
            prefix,
            Layout: isOperator ? AdminLayout : AppLayout,
            home: isOperator ? 'admin.dashboard' : 'dashboard',
            isOperator,
        }),
        [isOperator, may, panel, prefix, r],
    );
}
