import { PageHeader } from '@/components/app-shell/page-header';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell } from '@/components/data-table/data-table-cells';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { AdminLayout } from '@/layouts/admin-layout';
import type { TablePayload } from '@/types';
import { router } from '@inertiajs/react';
import { LifeBuoy, SearchX } from 'lucide-react';

interface TicketRow {
    id: number;
    uuid: string;
    number: string;
    subject: string;
    company: string | null;
    status_label: string;
    status_color: string;
    priority_label: string;
    priority_color: string;
    assignee: string | null;
    updated_at: string | null;
}

interface Props {
    table: TablePayload<TicketRow>;
}

const COLOR: Record<string, NonNullable<BadgeProps['variant']>> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    danger: 'destructive',
    neutral: 'secondary',
};

export default function AdminTicketsIndex({ table }: Props) {
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<TicketRow> = {
        number: (row) => (
            <button type="button" className="font-medium text-primary hover:underline" onClick={() => router.visit(route('admin.tickets.show', row.uuid))}>
                {row.number}
            </button>
        ),
        subject: (row) => <span className="font-medium">{row.subject}</span>,
        company: (row) => <TextCell value={row.company} />,
        status: (row) => <Badge variant={COLOR[row.status_color] ?? 'secondary'}>{row.status_label}</Badge>,
        priority: (row) => <Badge variant={COLOR[row.priority_color] ?? 'outline'}>{row.priority_label}</Badge>,
        assignee: (row) => <TextCell value={row.assignee ?? 'Unassigned'} muted={!row.assignee} />,
        updated_at: (row) => <RelativeDateCell value={row.updated_at} />,
    };

    return (
        <AdminLayout title="Tickets" breadcrumbs={[{ label: 'Tickets' }]}>
            <div className="space-y-6">
                <PageHeader title="Support tickets" description="Tenant requests across every workspace." />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="platform-tickets"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder="Search tickets or tenants…"
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : LifeBuoy}
                            title={filtered ? 'No matches' : 'Inbox is empty'}
                            className="border-0"
                        />
                    }
                />
            </div>
        </AdminLayout>
    );
}
