import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { CurrencyCell, TextCell } from '@/components/data-table/data-table-cells';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { GuestInvoiceRow } from '@/types/folio';
import { Receipt, SearchX } from 'lucide-react';

interface Props {
    table: TablePayload<GuestInvoiceRow>;
    can: { download: boolean };
}

export default function GuestInvoicesIndex({ table, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Guest invoices' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<GuestInvoiceRow> = {
        number: (row) => (
            <a href={route('guest-invoices.show', row.id)} target="_blank" rel="noreferrer" className="font-medium text-primary hover:underline">
                {row.number}
            </a>
        ),
        guest: (row) => <TextCell value={row.guest} />,
        reservation: (row) => <TextCell value={row.reservation} muted />,
        status: (row) => <Badge variant="outline">{row.status_label}</Badge>,
        total: (row) => <CurrencyCell value={row.total} minorUnits />,
        issued_at: (row) => <TextCell value={row.issued_at ? new Date(row.issued_at).toLocaleDateString() : null} muted />,
    };

    return (
        <AppLayout title="Guest invoices" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="Guest invoices" description="Printable guest bills generated when folios close at checkout." />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="guest-invoices"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder="Search invoices…"
                    rowActions={
                        can.download
                            ? (row) => [
                                  {
                                      id: 'download',
                                      label: 'Download',
                                      onSelect: () => window.open(route('guest-invoices.download', row.id), '_blank'),
                                  },
                              ]
                            : undefined
                    }
                    emptyState={<EmptyState icon={filtered ? SearchX : Receipt} title={filtered ? 'No matches' : 'No guest invoices yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
