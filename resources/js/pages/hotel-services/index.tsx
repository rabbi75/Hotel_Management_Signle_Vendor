import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { CurrencyCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { HotelServiceRow } from '@/types/folio';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, SearchX, Sparkles, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<HotelServiceRow>;
    can: { create: boolean };
}

export default function HotelServicesIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Hotel services' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: HotelServiceRow): Promise<void> {
        const ok = await confirm({ title: `Delete ${row.name}?`, variant: 'destructive', confirmLabel: 'Delete' });
        if (ok) router.delete(route('hotel-services.destroy', row.id), { preserveScroll: true });
    }

    const columns: ColumnRenderers<HotelServiceRow> = {
        name: (row) => <span className="font-medium">{row.name}</span>,
        hotel: (row) => <TextCell value={row.hotel ?? 'Workspace-wide'} muted />,
        category: (row) => <TextCell value={row.category_label} muted />,
        price: (row) => <CurrencyCell value={row.price} minorUnits />,
    };

    function rowActions(row: HotelServiceRow): RowAction[] {
        if (!allows('hotel_services.manage')) return [];
        return [
            { id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('hotel-services.edit', row.id)) },
            { id: 'delete', label: 'Delete', destructive: true, separatorBefore: true, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => void remove(row) },
        ];
    }

    return (
        <AppLayout title="Hotel services" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Hotel services"
                    description="Add-on charges that front desk can post to guest folios."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('hotel-services.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New service
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="hotel-services"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search services…"
                    emptyState={<EmptyState icon={filtered ? SearchX : Sparkles} title={filtered ? 'No matches' : 'No services yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
