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
import type { BedRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { BedDouble, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<BedRow>;
    can: { create: boolean };
}

export default function BedsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Beds' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: BedRow): Promise<void> {
        const ok = await confirm({ title: `Delete bed ${row.name}?`, variant: 'destructive', confirmLabel: 'Delete' });
        if (ok) router.delete(route('beds.destroy', row.id), { preserveScroll: true });
    }

    const columns: ColumnRenderers<BedRow> = {
        name: (row) => <span className="font-medium">{row.name}</span>,
        room: (row) => <TextCell value={row.room} muted />,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        bed_type: (row) => <TextCell value={row.bed_type} muted />,
        status: (row) => <Badge variant="outline">{row.status_label}</Badge>,
        price: (row) => <span className="tabular-nums">{row.price}</span>,
    };

    function rowActions(row: BedRow): RowAction[] {
        if (!allows('beds.manage')) return [];
        return [
            { id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('beds.edit', row.id)) },
            { id: 'delete', label: 'Delete', destructive: true, separatorBefore: true, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => void remove(row) },
        ];
    }

    return (
        <AppLayout title="Beds" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Beds"
                    description="For hostels and shared rooms."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('beds.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New bed
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="beds"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search beds…"
                    emptyState={<EmptyState icon={filtered ? SearchX : BedDouble} title={filtered ? 'No matches' : 'No beds yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
