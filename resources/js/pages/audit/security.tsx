import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell } from '@/components/data-table/data-table-cells';
import type { ExportFormat } from '@/components/data-table/data-table-view-options';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { SecurityEventOption, SecurityRow, SeverityOption } from '@/types/audit';
import { format, isValid, parseISO } from 'date-fns';
import { SearchX, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { DetailRow, JsonView, auditExportUrl } from './json-view';

interface SecurityLogPageProps {
    table: TablePayload<SecurityRow>;
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

export default function AuditSecurity({ table, events, severities }: SecurityLogPageProps) {
    const { can } = usePermissions();
    const [selected, setSelected] = useState<SecurityRow | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Audit' },
        { label: 'Security' },
    ];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const eventLabels = new Map(events.map((event) => [event.value, event.label]));
    const severityLabels = new Map(severities.map((severity) => [severity.value, severity.label]));

    const columns: ColumnRenderers<SecurityRow> = {
        description: (row) => <TextCell value={row.description} className="font-medium" />,
        event: (row) => <Badge variant="outline">{row.event_label || (eventLabels.get(row.event) ?? row.event)}</Badge>,
        severity: (row) => (
            <Badge variant={SEVERITY_VARIANT[row.severity_color] ?? 'secondary'}>{severityLabels.get(row.severity) ?? row.severity}</Badge>
        ),
        user: (row) => <TextCell value={row.user ?? 'System'} />,
        ip_address: (row) => <TextCell value={row.ip_address} muted className="font-mono text-xs" />,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
    };

    function onExport(exportFormat: ExportFormat): void {
        if (exportFormat === 'print') {
            window.print();

            return;
        }

        window.location.assign(auditExportUrl(route('audit.security.export'), exportFormat === 'excel' ? 'xlsx' : 'csv'));
    }

    return (
        <AppLayout title="Security log" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Security log"
                    description="Password changes, two-factor enrolment, permission grants and impersonation."
                />

                <DataTable<SecurityRow>
                    payload={table}
                    propKey="table"
                    name="security"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    onRowClick={(row) => setSelected(row)}
                    primaryColumn="description"
                    searchPlaceholder="Search descriptions, users or IPs…"
                    caption="Security-relevant events"
                    {...(can('audit.export') ? { onExport } : {})}
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No events match these filters"
                                description="Widen the date range or clear the severity filter."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={ShieldAlert}
                                title="No security events recorded"
                                description="Sensitive actions are written here the moment they happen."
                                className="border-0"
                            />
                        )
                    }
                />
            </div>

            <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Security event</SheetTitle>
                        <SheetDescription>{selected?.description}</SheetDescription>
                    </SheetHeader>

                    {selected && (
                        <div className="space-y-6 px-6 pb-6">
                            <dl className="divide-y divide-border">
                                <DetailRow label="Event">
                                    <Badge variant="outline">{selected.event_label || selected.event}</Badge>
                                </DetailRow>
                                <DetailRow label="Severity">
                                    <Badge variant={SEVERITY_VARIANT[selected.severity_color] ?? 'secondary'}>
                                        {severityLabels.get(selected.severity) ?? selected.severity}
                                    </Badge>
                                </DetailRow>
                                <DetailRow label="User">{selected.user ?? 'System'}</DetailRow>
                                <DetailRow label="IP address">
                                    <span className="font-mono text-xs">{selected.ip_address ?? '—'}</span>
                                </DetailRow>
                                <DetailRow label="When">{absolute(selected.created_at)}</DetailRow>
                            </dl>

                            <section className="space-y-2">
                                <h3 className="text-sm font-semibold">Context</h3>
                                <div className="rounded-lg border border-border p-3">
                                    <JsonView value={selected.context ?? {}} />
                                </div>
                            </section>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
