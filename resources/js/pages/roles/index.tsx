import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { RoleSummary } from '@/types/roles';
import { Link, router } from '@inertiajs/react';
import { Grid3x3, Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';

interface RolesIndexProps {
    table: TablePayload<RoleSummary>;
    can: { create: boolean; update_permissions: boolean };
}

export default function RolesIndex({ table, can }: RolesIndexProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Roles' }];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<RoleSummary> = {
        name: (row) => (
            <div className="flex min-w-0 items-center gap-2">
                <Link
                    href={route('roles.show', row.id)}
                    className="truncate rounded-sm text-sm font-medium outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                >
                    {row.label}
                </Link>
                {row.is_system && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>
                                <Lock className="size-3.5 text-muted-foreground" aria-label="System role" />
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>Declared in the permission registry; it cannot be deleted.</TooltipContent>
                    </Tooltip>
                )}
            </div>
        ),
        guard_name: (row) => <TextCell value={row.guard_name} muted />,
        users_count: (row) => <span className="tabular-nums">{row.users_count ?? 0}</span>,
        permissions_count: (row) =>
            row.is_super_admin ? <Badge variant="warning">All</Badge> : <span className="tabular-nums">{row.permissions_count ?? 0}</span>,
        created_at: (row) => <DateCell value={row.created_at} />,
    };

    function rowActions(row: RoleSummary): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'view',
                label: 'View',
                icon: <ShieldCheck className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('roles.show', row.id)),
            },
        ];

        if (allows('roles.update')) {
            actions.push({
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                disabled: row.is_super_admin,
                onSelect: () => router.visit(route('roles.edit', row.id)),
            });
        }

        if (allows('roles.delete')) {
            const blocked = row.is_super_admin || row.is_system || (row.users_count ?? 0) > 0;

            actions.push({
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                disabled: blocked,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: async () => {
                    const ok = await confirm({
                        title: `Delete the “${row.label}” role?`,
                        description: 'Any future assignment of this role by name will fail.',
                        variant: 'destructive',
                        confirmWord: row.name,
                        confirmLabel: 'Delete role',
                    });

                    if (ok) {
                        router.delete(route('roles.destroy', row.id), { preserveScroll: true });
                    }
                },
            });
        }

        return actions;
    }

    return (
        <AppLayout title="Roles" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Roles"
                    description="Named bundles of permissions you assign to people."
                    actions={
                        <>
                            {can.update_permissions && allows('roles.update') && (
                                <Button asChild variant="outline">
                                    <Link href={route('roles.permissions.show')}>
                                        <Grid3x3 className="size-4" aria-hidden="true" />
                                        Permission matrix
                                    </Link>
                                </Button>
                            )}
                            {can.create && allows('roles.create') && (
                                <Button asChild>
                                    <Link href={route('roles.create')}>
                                        <Plus className="size-4" aria-hidden="true" />
                                        New role
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <DataTable<RoleSummary>
                    payload={table}
                    propKey="table"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search roles…"
                    caption="Roles defined for this application"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={ShieldCheck}
                                title="No roles match this search"
                                description="Clear the search to see every role."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={ShieldCheck}
                                title="No roles yet"
                                description="Create a role to start granting capabilities to people."
                                className="border-0"
                                action={
                                    can.create && allows('roles.create') ? (
                                        <Button asChild size="sm">
                                            <Link href={route('roles.create')}>
                                                <Plus className="size-4" aria-hidden="true" />
                                                New role
                                            </Link>
                                        </Button>
                                    ) : null
                                }
                            />
                        )
                    }
                />
            </div>
        </AppLayout>
    );
}
