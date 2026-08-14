import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, type RowAction } from '@/components/data-table/data-table-cells';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { Invoice } from '@/types/billing';
import { Download, Eye, Receipt, SearchX } from 'lucide-react';
import { Amount, StatusBadge } from './billing-ui';

interface InvoicesPageProps {
    table: TablePayload<Invoice>;
    can: { download: boolean };
}

export default function BillingInvoices({ table, can }: InvoicesPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Billing', href: routeUrl('billing.index') ?? undefined },
        { label: 'Invoices' },
    ];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<Invoice> = {
        number: (row) => <span className="block truncate text-sm font-medium">{row.number}</span>,
        status: (row) => <StatusBadge label={row.status_label} color={row.status_color} />,
        total: (row) => <Amount value={row.total_formatted} className="text-sm font-medium" />,
        issued_at: (row) => <DateCell value={row.issued_at} />,
        due_at: (row) => <DateCell value={row.due_at} />,
        paid_at: (row) => <DateCell value={row.paid_at} />,
    };

    function rowActions(row: Invoice): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'view',
                label: 'View',
                icon: <Eye className="size-4 opacity-70" aria-hidden="true" />,
                // A full page load, not an Inertia visit: the invoice is a
                // standalone printable document, not an application screen.
                onSelect: () => {
                    window.open(route('billing.invoices.show', row.id), '_blank', 'noopener');
                },
            },
        ];

        if (can.download) {
            actions.push({
                id: 'download',
                label: 'Download',
                icon: <Download className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => window.location.assign(route('billing.invoices.download', row.id)),
            });
        }

        return actions;
    }

    return (
        <AppLayout title="Invoices" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Invoices"
                    description="Every invoice raised against this workspace."
                    actions={
                        <Button asChild variant="outline">
                            <a href={routeUrl('billing.index') ?? '#'}>Back to billing</a>
                        </Button>
                    }
                />

                <DataTable<Invoice>
                    payload={table}
                    propKey="table"
                    name="invoices"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search by invoice number…"
                    caption="Invoices for this workspace"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No invoices match these filters"
                                description="Clear the status or date filters to see everything."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={Receipt}
                                title="No invoices yet"
                                description="Invoices appear here as soon as the first period is billed."
                                className="border-0"
                            />
                        )
                    }
                />
            </div>
        </AppLayout>
    );
}
