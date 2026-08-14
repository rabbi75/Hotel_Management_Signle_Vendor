import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell } from '@/components/data-table/data-table-cells';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import { Link, router } from '@inertiajs/react';
import { LifeBuoy, Plus, SearchX } from 'lucide-react';

interface TicketRow {
    id: number;
    uuid: string;
    number: string;
    subject: string;
    status: string;
    status_label: string;
    status_color: string;
    priority: string;
    priority_label: string;
    priority_color: string;
    category_label: string;
    updated_at: string | null;
}

interface Props {
    table: TablePayload<TicketRow>;
    can: { create: boolean };
}

const COLOR: Record<string, NonNullable<BadgeProps['variant']>> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    danger: 'destructive',
    neutral: 'secondary',
};

export default function SupportIndex({ table, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Support' },
    ];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<TicketRow> = {
        number: (row) => (
            <button type="button" className="font-medium text-primary hover:underline" onClick={() => router.visit(route('support.show', row.uuid))}>
                {row.number}
            </button>
        ),
        subject: (row) => <span className="font-medium">{row.subject}</span>,
        status: (row) => <Badge variant={COLOR[row.status_color] ?? 'secondary'}>{row.status_label}</Badge>,
        priority: (row) => <Badge variant={COLOR[row.priority_color] ?? 'outline'}>{row.priority_label}</Badge>,
        category: (row) => <TextCell value={row.category_label} muted />,
        updated_at: (row) => <RelativeDateCell value={row.updated_at} />,
    };

    return (
        <AppLayout title="Support" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Support"
                    description="Open a ticket with the platform team for billing, access, or product help."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('support.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New ticket
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="support-tickets"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder="Search tickets…"
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : LifeBuoy}
                            title={filtered ? 'No matches' : 'No tickets yet'}
                            description={filtered ? undefined : 'Start a conversation with platform support.'}
                            className="border-0"
                        />
                    }
                />
            </div>
        </AppLayout>
    );
}
