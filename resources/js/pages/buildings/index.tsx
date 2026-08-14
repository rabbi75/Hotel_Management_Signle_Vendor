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
import type { BuildingRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { Building, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<BuildingRow>;
    can: { create: boolean };
}

export default function BuildingsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Buildings' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: BuildingRow): Promise<void> {
        const ok = await confirm({ title: `Delete ${row.name}?`, variant: 'destructive', confirmLabel: 'Delete' });
        if (ok) router.delete(route('buildings.destroy', row.id), { preserveScroll: true });
    }

    const columns: ColumnRenderers<BuildingRow> = {
        name: (row) => <span className="font-medium">{row.name}</span>,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        code: (row) => <TextCell value={row.code} muted />,
        floors_count: (row) => <span className="tabular-nums">{row.floors_count ?? 0}</span>,
    };

    function rowActions(row: BuildingRow): RowAction[] {
        if (!allows('buildings.manage')) return [];
        return [
            { id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('buildings.edit', row.id)) },
            { id: 'delete', label: 'Delete', destructive: true, separatorBefore: true, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => void remove(row) },
        ];
    }

    return (
        <AppLayout title="Buildings" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Buildings"
                    description="Optional wings or blocks within a property."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('buildings.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New building
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="buildings"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search buildings…"
                    emptyState={<EmptyState icon={filtered ? SearchX : Building} title={filtered ? 'No matches' : 'No buildings yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
