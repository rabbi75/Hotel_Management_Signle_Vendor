import { usePermissions } from '@/hooks/use-permissions';
import { AdminLayout } from '@/layouts/admin-layout';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

/*
|------------------------------------------------------------------------------
| Which panel is the page editor running in?
|------------------------------------------------------------------------------
|
| The same editor serves a tenant editing their workspace's pages and an
| operator editing the platform's public site. The three things that differ —
| the route names, the shell, and which permission grants each action — are
| resolved here so nothing downstream has to branch.
|
| The server sends `panel: 'admin'` from the console controllers; its absence
| means the tenant app, which keeps every existing render unchanged.
|
*/

export type Panel = 'app' | 'admin';

export type PageAction = 'view' | 'create' | 'update' | 'delete' | 'publish';

/** The tenant permission behind each action; the console gates all five on one. */
const TENANT_PERMISSION: Record<PageAction, string> = {
    view: 'cms.pages.view',
    create: 'cms.pages.create',
    update: 'cms.pages.update',
    delete: 'cms.pages.delete',
    publish: 'cms.pages.publish',
};

const OPERATOR_PERMISSION = 'platform.pages.manage';

export function usePanel() {
    const { auth, ...props } = usePage<SharedProps & { panel?: Panel }>().props;
    const panel: Panel = props.panel === 'admin' ? 'admin' : 'app';
    const { can } = usePermissions();

    const isOperator = panel === 'admin';
    const isSuperOperator = Boolean(auth.admin?.is_super_admin);

    const prefix = isOperator ? 'admin.cms.' : 'cms.';

    /** A route name in whichever panel this is, e.g. `r('pages.edit', id)`. */
    const r = useCallback(
        (name: string, params?: Parameters<typeof route>[1]): string => route(`${prefix}${name}`, params),
        [prefix],
    );

    const may = useCallback(
        (action: PageAction): boolean =>
            isOperator ? isSuperOperator || can(OPERATOR_PERMISSION) : can(TENANT_PERMISSION[action]),
        [can, isOperator, isSuperOperator],
    );

    return useMemo(
        () => ({
            panel,
            r,
            may,
            /** For the guarded `routeUrl()` lookups, which take a name rather than args. */
            prefix,
            Layout: isOperator ? AdminLayout : AppLayout,
            /** Where the breadcrumb trail starts in this panel. */
            home: isOperator ? 'admin.dashboard' : 'dashboard',
            /** The list caption differs: a workspace's pages, or the public site's. */
            scopeLabel: isOperator ? 'Pages on the public site' : 'Pages in this workspace',
        }),
        [isOperator, may, panel, prefix, r],
    );
}
