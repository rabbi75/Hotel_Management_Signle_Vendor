import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { HotelRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { Building2, Eye, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<HotelRow>;
    can: { create: boolean };
}

export default function HotelsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Hotels' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: HotelRow): Promise<void> {
        const ok = await confirm({
            title: `Delete ${row.name}?`,
            description: 'Related rooms and property structure under this hotel will be removed.',
            variant: 'destructive',
            confirmLabel: 'Delete hotel',
        });
        if (ok) {
            router.delete(route('hotels.destroy', row.uuid), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<HotelRow> = {
        name: (row) => (
            <Link href={route('hotels.show', row.uuid)} className="font-medium underline-offset-4 hover:underline">
                {row.name}
            </Link>
        ),
        city: (row) => <TextCell value={row.city} muted />,
        status: (row) => <Badge variant="outline">{row.status_label}</Badge>,
        rooms_count: (row) => <span className="tabular-nums">{row.rooms_count ?? 0}</span>,
        created_at: (row) => <DateCell value={row.created_at} />,
    };

    function rowActions(row: HotelRow): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'view',
                label: 'View',
                icon: <Eye className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('hotels.show', row.uuid)),
            },
        ];
        if (allows('hotels.update')) {
            actions.push({
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('hotels.edit', row.uuid)),
            });
        }
        if (allows('hotels.delete')) {
            actions.push({
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            });
        }
        return actions;
    }

    return (
        <AppLayout title="Hotels" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Hotels"
                    description="Properties managed by this workspace."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('hotels.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New hotel
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="hotels"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search hotels…"
                    caption="Hotels in this workspace"
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : Building2}
                            title={filtered ? 'No hotels match these filters' : 'No hotels yet'}
                            description={filtered ? 'Clear filters to see every property.' : 'Create your first hotel or property to begin.'}
                            className="border-0"
                        />
                    }
                />
            </div>
        </AppLayout>
    );
}
