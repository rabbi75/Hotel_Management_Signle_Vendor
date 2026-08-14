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
import type { RoomTypeRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { LayoutGrid, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<RoomTypeRow>;
    can: { create: boolean };
}

export default function RoomTypesIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Room types' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: RoomTypeRow): Promise<void> {
        const ok = await confirm({ title: `Delete ${row.name}?`, variant: 'destructive', confirmLabel: 'Delete' });
        if (ok) router.delete(route('room-types.destroy', row.id), { preserveScroll: true });
    }

    const columns: ColumnRenderers<RoomTypeRow> = {
        name: (row) => <span className="font-medium">{row.name}</span>,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        base_price: (row) => <span className="tabular-nums">{row.base_price}</span>,
        max_occupancy: (row) => <span className="tabular-nums">{row.max_occupancy}</span>,
        rooms_count: (row) => <span className="tabular-nums">{row.rooms_count ?? 0}</span>,
    };

    function rowActions(row: RoomTypeRow): RowAction[] {
        if (!allows('room_types.manage')) return [];
        return [
            { id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('room-types.edit', row.id)) },
            { id: 'delete', label: 'Delete', destructive: true, separatorBefore: true, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => void remove(row) },
        ];
    }

    return (
        <AppLayout title="Room types" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Room types"
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('room-types.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New room type
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="room-types"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search room types…"
                    emptyState={<EmptyState icon={filtered ? SearchX : LayoutGrid} title={filtered ? 'No matches' : 'No room types yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
