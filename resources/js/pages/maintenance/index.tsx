import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { TextCell } from '@/components/data-table/data-table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { MaintenanceRequestRow } from '@/types/operations';
import { Link, router } from '@inertiajs/react';
import { Plus, SearchX, Wrench } from 'lucide-react';

interface Props {
    table: TablePayload<MaintenanceRequestRow>;
    can: { create: boolean };
}

export default function MaintenanceIndex({ table, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Maintenance' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<MaintenanceRequestRow> = {
        number: (row) => (
            <button type="button" className="font-medium text-primary hover:underline" onClick={() => router.visit(route('maintenance.show', row.id))}>
                {row.number}
            </button>
        ),
        title: (row) => <span className="font-medium">{row.title}</span>,
        room: (row) => <TextCell value={row.room} muted />,
        status: (row) => <Badge variant="outline">{row.status_label}</Badge>,
        priority: (row) => <Badge variant="outline">{row.priority_label}</Badge>,
        category: (row) => <TextCell value={row.category_label} muted />,
        due_at: (row) => <TextCell value={row.due_at ? new Date(row.due_at).toLocaleDateString() : null} muted />,
    };

    return (
        <AppLayout title="Maintenance" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Maintenance"
                    description="Work orders for room repairs — blocking orders take rooms off the market."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('maintenance.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    Report issue
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="maintenance-requests"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder="Search work orders…"
                    emptyState={<EmptyState icon={filtered ? SearchX : Wrench} title={filtered ? 'No matches' : 'No work orders yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
