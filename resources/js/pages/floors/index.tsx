import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { FloorRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { Layers, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<FloorRow>;
    can: { create: boolean };
}

export default function FloorsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Floors' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: FloorRow): Promise<void> {
        const ok = await confirm({ title: `Delete ${row.name}?`, variant: 'destructive', confirmLabel: 'Delete' });
        if (ok) router.delete(route('floors.destroy', row.id), { preserveScroll: true });
    }

    const columns: ColumnRenderers<FloorRow> = {
        name: (row) => <span className="font-medium">{row.name}</span>,
        floor_number: (row) => <span className="tabular-nums">{row.floor_number}</span>,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        building: (row) => <TextCell value={row.building} muted />,
        rooms_count: (row) => <span className="tabular-nums">{row.rooms_count ?? 0}</span>,
    };

    function rowActions(row: FloorRow): RowAction[] {
        if (!allows('floors.manage')) return [];
        return [
            { id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('floors.edit', row.id)) },
            { id: 'delete', label: 'Delete', destructive: true, separatorBefore: true, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => void remove(row) },
        ];
    }

    return (
        <AppLayout title="Floors" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Floors"
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('floors.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New floor
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="floors"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search floors…"
                    emptyState={<EmptyState icon={filtered ? SearchX : Layers} title={filtered ? 'No matches' : 'No floors yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
