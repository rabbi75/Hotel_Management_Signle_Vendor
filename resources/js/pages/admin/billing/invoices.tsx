import { PageHeader } from '@/components/app-shell/page-header';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { AdminLayout } from '@/layouts/admin-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { Invoice } from '@/types/billing';
import { Download, FileText, Receipt, SearchX } from 'lucide-react';

const STATUS_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    paid: 'success',
    open: 'warning',
    draft: 'secondary',
    void: 'outline',
    refunded: 'outline',
    uncollectible: 'destructive',
};

interface AdminInvoicesProps {
    table: TablePayload<Invoice>;
    can: { refund: boolean };
}

/**
 * Every invoice the installation has issued.
 *
 * The document behind "View" is the same Blade view the customer receives — an
 * operator reading a different invoice from the one in the customer's inbox is
 * how a billing dispute becomes unresolvable.
 */
export default function AdminInvoicesPage({ table }: AdminInvoicesProps) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Platform' }, { label: 'Invoices' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<Invoice> = {
        number: (row) => <span className="font-mono text-xs font-medium text-foreground">{row.number}</span>,
        company: (row) => <TextCell value={row.company} />,
        status: (row) => <Badge variant={STATUS_VARIANT[row.status] ?? 'secondary'}>{row.status_label}</Badge>,
        total: (row) => <span className="tabular-nums">{row.total_formatted}</span>,
        issued_at: (row) => <DateCell value={row.issued_at} />,
        due_at: (row) => <DateCell value={row.due_at} />,
        paid_at: (row) => <DateCell value={row.paid_at} />,
    };

    function rowActions(row: Invoice): RowAction[] {
        return [
            {
                id: 'view',
                label: 'View invoice',
                icon: <FileText className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => {
                    window.open(route('admin.invoices.show', row.id), '_blank', 'noreferrer');
                },
            },
            {
                id: 'download',
                label: 'Download',
                icon: <Download className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => {
                    window.open(route('admin.invoices.download', row.id), '_blank', 'noreferrer');
                },
            },
        ];
    }

    return (
        <AdminLayout title="Invoices" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="Invoices" description="Every invoice issued, across every workspace." />

                <DataTable<Invoice>
                    payload={table}
                    propKey="table"
                    name="invoices"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search by invoice number…"
                    caption="Invoices across all workspaces"
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : Receipt}
                            title={filtered ? 'No invoices match those filters' : 'No invoices yet'}
                            description={
                                filtered
                                    ? 'Try clearing the search or filters.'
                                    : 'Invoices appear here once a workspace is billed.'
                            }
                            className="border-0"
                        />
                    }
                />
            </div>
        </AdminLayout>
    );
}
