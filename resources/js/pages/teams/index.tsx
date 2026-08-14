import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { OptionMap, TeamRow } from '@/types/companies';
import { Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, SearchX, Trash2, UsersRound } from 'lucide-react';

interface TeamsIndexProps {
    table: TablePayload<TeamRow>;
    departments: OptionMap;
    can: { create: boolean };
}

export default function TeamsIndex({ table, departments, can }: TeamsIndexProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Teams' }];

    const mayManage = allows('companies.teams.manage');
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<TeamRow> = {
        name: (row) => (
            <span className="flex min-w-0 items-center gap-2">
                {row.color && <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: row.color }} aria-hidden="true" />}
                <Link
                    href={route('teams.show', row.id)}
                    className="truncate rounded-sm text-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                >
                    {row.name}
                </Link>
            </span>
        ),
        department: (row) => <TextCell value={row.department ?? departments[String(row.department_id ?? '')] ?? null} />,
        lead: (row) => <TextCell value={row.lead} />,
        members_count: (row) => <span className="tabular-nums">{row.members_count ?? 0}</span>,
        created_at: (row) => <DateCell value={row.created_at} />,
    };

    function rowActions(row: TeamRow): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'view',
                label: 'View',
                icon: <Eye className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('teams.show', row.id)),
            },
        ];

        if (mayManage) {
            actions.push(
                {
                    id: 'edit',
                    label: 'Edit',
                    icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                    onSelect: () => router.visit(route('teams.edit', row.id)),
                },
                {
                    id: 'delete',
                    label: 'Delete',
                    destructive: true,
                    separatorBefore: true,
                    icon: <Trash2 className="size-4" aria-hidden="true" />,
                    onSelect: async () => {
                        const ok = await confirm({
                            title: `Delete the ${row.name} team?`,
                            description: 'Members stay in the workspace but lose this team assignment.',
                            variant: 'destructive',
                            confirmLabel: 'Delete team',
                        });

                        if (ok) {
                            router.delete(route('teams.destroy', row.id), { preserveScroll: true });
                        }
                    },
                },
            );
        }

        return actions;
    }

    const newTeam =
        can.create && mayManage ? (
            <Button asChild>
                <Link href={route('teams.create')}>
                    <Plus className="size-4" aria-hidden="true" />
                    New team
                </Link>
            </Button>
        ) : null;

    return (
        <AppLayout title="Teams" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Teams"
                    description="Working groups inside this workspace, optionally attached to a department."
                    actions={newTeam}
                />

                <DataTable<TeamRow>
                    payload={table}
                    propKey="table"
                    name="teams"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search teams…"
                    caption="Teams in this workspace"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No teams match these filters"
                                description="Clear the department filter to see every team."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={UsersRound}
                                title="No teams yet"
                                description="Create a team to group people around a shared piece of work."
                                className="border-0"
                                action={
                                    can.create && mayManage ? (
                                        <Button asChild size="sm">
                                            <Link href={route('teams.create')}>
                                                <Plus className="size-4" aria-hidden="true" />
                                                New team
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
