import { PageHeader } from '@/components/app-shell/page-header';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell } from '@/components/data-table/data-table-cells';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { AdminLayout } from '@/layouts/admin-layout';
import type { TablePayload } from '@/types';
import type { SecurityEventOption, SecurityRow, SeverityOption } from '@/types/audit';
import { format, isValid, parseISO } from 'date-fns';
import { SearchX, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

interface PlatformAuditPageProps {
    table: TablePayload<SecurityRow & { admin?: string | null; company?: string | null }>;
    events: SecurityEventOption[];
    severities: SeverityOption[];
}

const SEVERITY_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    info: 'info',
    neutral: 'secondary',
    warning: 'warning',
    danger: 'destructive',
};

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PPpp') : '—';
}

export default function PlatformAudit({ table, events, severities }: PlatformAuditPageProps) {
    const [selected, setSelected] = useState<(SecurityRow & { admin?: string | null; company?: string | null }) | null>(null);

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;
    const eventLabels = new Map(events.map((event) => [event.value, event.label]));
    const severityLabels = new Map(severities.map((severity) => [severity.value, severity.label]));

    const columns: ColumnRenderers<SecurityRow & { admin?: string | null; company?: string | null }> = {
        description: (row) => <TextCell value={row.description} className="font-medium" />,
        event: (row) => <Badge variant="outline">{row.event_label || (eventLabels.get(row.event) ?? row.event)}</Badge>,
        severity: (row) => (
            <Badge variant={SEVERITY_VARIANT[row.severity_color] ?? 'secondary'}>
                {severityLabels.get(row.severity) ?? row.severity}
            </Badge>
        ),
        admin: (row) => <TextCell value={row.admin ?? '—'} />,
        company: (row) => <TextCell value={row.company ?? '—'} />,
        user: (row) => <TextCell value={row.user ?? '—'} />,
        ip_address: (row) => <TextCell value={row.ip_address} muted className="font-mono text-xs" />,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
    };

    return (
        <AdminLayout title="Audit log" breadcrumbs={[{ label: 'System' }, { label: 'Audit' }]}>
            <div className="space-y-6">
                <PageHeader
                    title="Platform audit"
                    description="Impersonations, plan changes, suspends, AI adjustments, and settings changes across tenants."
                />

                <DataTable
                    payload={table}
                    propKey="table"
                    name="platform-audit"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    onRowClick={(row) => setSelected(row)}
                    primaryColumn="description"
                    searchPlaceholder="Search descriptions, operators or tenants…"
                    caption="Platform security events"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No events match these filters"
                                description="Widen the date range or clear the event filter."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={ShieldAlert}
                                title="No audit events yet"
                                description="Operator actions are written here as they happen."
                                className="border-0"
                            />
                        )
                    }
                />
            </div>

            <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Audit event</SheetTitle>
                        <SheetDescription>{selected?.description}</SheetDescription>
                    </SheetHeader>

                    {selected && (
                        <div className="space-y-4 px-6 pb-6 text-sm">
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Event</span>
                                <Badge variant="outline">{selected.event_label || selected.event}</Badge>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Operator</span>
                                <span>{selected.admin ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Tenant</span>
                                <span>{selected.company ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">User</span>
                                <span>{selected.user ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">IP</span>
                                <span className="font-mono text-xs">{selected.ip_address ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">When</span>
                                <span>{absolute(selected.created_at)}</span>
                            </div>
                            <pre className="overflow-x-auto rounded-lg border border-border bg-muted/40 p-3 text-xs">
                                {JSON.stringify(selected.context ?? {}, null, 2)}
                            </pre>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AdminLayout>
    );
}
