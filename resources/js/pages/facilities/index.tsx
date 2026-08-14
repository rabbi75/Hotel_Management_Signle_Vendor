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
import type { FacilityRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, SearchX, Sparkles, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<FacilityRow>;
    can: { create: boolean };
}

export default function FacilitiesIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Facilities' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: FacilityRow): Promise<void> {
        const ok = await confirm({ title: `Delete ${row.name}?`, variant: 'destructive', confirmLabel: 'Delete' });
        if (ok) router.delete(route('facilities.destroy', row.id), { preserveScroll: true });
    }

    const columns: ColumnRenderers<FacilityRow> = {
        name: (row) => <span className="font-medium">{row.name}</span>,
        hotel: (row) => <TextCell value={row.hotel ?? 'Workspace-wide'} muted />,
        code: (row) => <TextCell value={row.code} muted />,
        icon: (row) => <TextCell value={row.icon} muted />,
    };

    function rowActions(row: FacilityRow): RowAction[] {
        if (!allows('facilities.manage')) return [];
        return [
            { id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('facilities.edit', row.id)) },
            { id: 'delete', label: 'Delete', destructive: true, separatorBefore: true, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => void remove(row) },
        ];
    }

    return (
        <AppLayout title="Facilities" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Facilities"
                    description="Amenities reusable across hotels, room types and rooms."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('facilities.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New facility
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="facilities"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search facilities…"
                    emptyState={<EmptyState icon={filtered ? SearchX : Sparkles} title={filtered ? 'No matches' : 'No facilities yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
