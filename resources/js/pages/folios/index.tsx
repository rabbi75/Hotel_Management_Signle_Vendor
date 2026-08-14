import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { CurrencyCell, TextCell } from '@/components/data-table/data-table-cells';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { GuestFolioRow } from '@/types/folio';
import { router } from '@inertiajs/react';
import { SearchX, Wallet } from 'lucide-react';

interface Props {
    table: TablePayload<GuestFolioRow>;
}

export default function FoliosIndex({ table }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Guest folios' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<GuestFolioRow> = {
        number: (row) => (
            <button type="button" className="font-medium text-primary hover:underline" onClick={() => router.visit(route('folios.show', row.id))}>
                {row.number}
            </button>
        ),
        guest: (row) => <TextCell value={row.guest} />,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        status: (row) => <Badge variant="outline">{row.status_label}</Badge>,
        total: (row) => <CurrencyCell value={row.total} minorUnits />,
        balance: (row) => <CurrencyCell value={row.balance} minorUnits />,
    };

    return (
        <AppLayout title="Guest folios" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="Guest folios" description="Open folios for in-house guests; charges and payments settle here before checkout." />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="folios"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder="Search folios…"
                    emptyState={<EmptyState icon={filtered ? SearchX : Wallet} title={filtered ? 'No matches' : 'No folios yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
