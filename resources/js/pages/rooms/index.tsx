import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { RoomRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { DoorOpen, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<RoomRow>;
    can: { create: boolean };
}

export default function RoomsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Rooms' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: RoomRow): Promise<void> {
        const ok = await confirm({ title: `Delete room ${row.number}?`, variant: 'destructive', confirmLabel: 'Delete' });
        if (ok) router.delete(route('rooms.destroy', row.id), { preserveScroll: true });
    }

    const columns: ColumnRenderers<RoomRow> = {
        number: (row) => <span className="font-medium">{row.number}</span>,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        room_type: (row) => <TextCell value={row.room_type} muted />,
        floor: (row) => <TextCell value={row.floor} muted />,
        status: (row) => <Badge variant="outline">{row.status_label}</Badge>,
        beds_count: (row) => <span className="tabular-nums">{row.beds_count ?? 0}</span>,
    };

    function rowActions(row: RoomRow): RowAction[] {
        const actions: RowAction[] = [];
        if (allows('rooms.update')) {
            actions.push({ id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('rooms.edit', row.id)) });
        }
        if (allows('rooms.delete')) {
            actions.push({ id: 'delete', label: 'Delete', destructive: true, separatorBefore: true, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => void remove(row) });
        }
        return actions;
    }

    return (
        <AppLayout title="Rooms" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Rooms"
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('rooms.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New room
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="rooms"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search rooms…"
                    emptyState={<EmptyState icon={filtered ? SearchX : DoorOpen} title={filtered ? 'No matches' : 'No rooms yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
