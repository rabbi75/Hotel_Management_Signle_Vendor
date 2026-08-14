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
import type { ActivityRow } from '@/types/audit';
import { format, isValid, parseISO } from 'date-fns';
import { ScrollText, SearchX } from 'lucide-react';
import { useState } from 'react';
import { DetailRow, JsonView, auditExportUrl } from './json-view';

interface ActivityPageProps {
    table: TablePayload<ActivityRow>;
}

const EVENT_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    created: 'success',
    updated: 'info',
    deleted: 'destructive',
    restored: 'warning',
};

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PPpp') : '—';
}

export default function AuditActivity({ table }: ActivityPageProps) {
    const { can } = usePermissions();
    const [selected, setSelected] = useState<ActivityRow | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Audit' },
        { label: 'Activity' },
    ];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<ActivityRow> = {
        id: (row) => <TextCell value={String(row.id)} muted />,
        description: (row) => <TextCell value={row.description} className="font-medium" />,
        event: (row) =>
            row.event ? <Badge variant={EVENT_VARIANT[row.event] ?? 'secondary'}>{row.event}</Badge> : <TextCell value="—" muted />,
        log_name: (row) => <TextCell value={row.log_name} muted />,
        subject_type: (row) => (
            <TextCell value={row.subject_type ? `${row.subject_type}${row.subject_id ? ` #${row.subject_id}` : ''}` : '—'} />
        ),
        causer: (row) => <TextCell value={row.causer} />,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
    };

    function onExport(exportFormat: ExportFormat): void {
        if (exportFormat === 'print') {
            window.print();

            return;
        }

        window.location.assign(auditExportUrl(route('audit.activity.export'), exportFormat === 'excel' ? 'xlsx' : 'csv'));
    }

    return (
        <AppLayout title="Activity log" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="Activity log" description="Every recorded change to a model, and who made it." />

                <DataTable<ActivityRow>
                    payload={table}
                    propKey="table"
                    name="activity"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    onRowClick={(row) => setSelected(row)}
                    primaryColumn="description"
                    searchPlaceholder="Search descriptions…"
                    caption="Model change history"
                    {...(can('audit.export') ? { onExport } : {})}
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No activity matches these filters"
                                description="Widen the date range or clear the event filter."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={ScrollText}
                                title="No activity recorded yet"
                                description="Changes appear here as soon as an auditable model is created, updated or deleted."
                                className="border-0"
                            />
                        )
                    }
                />
            </div>

            <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Activity detail</SheetTitle>
                        <SheetDescription>{selected?.description}</SheetDescription>
                    </SheetHeader>

                    {selected && (
                        <div className="space-y-6 px-6 pb-6">
                            <dl className="divide-y divide-border">
                                <DetailRow label="Event">
                                    {selected.event ? (
                                        <Badge variant={EVENT_VARIANT[selected.event] ?? 'secondary'}>{selected.event}</Badge>
                                    ) : (
                                        '—'
                                    )}
                                </DetailRow>
                                <DetailRow label="Log">{selected.log_name ?? '—'}</DetailRow>
                                <DetailRow label="Subject">
                                    {selected.subject_type
                                        ? `${selected.subject_type}${selected.subject_id ? ` #${selected.subject_id}` : ''}`
                                        : '—'}
                                </DetailRow>
                                <DetailRow label="Caused by">{selected.causer ?? 'System'}</DetailRow>
                                <DetailRow label="When">{absolute(selected.created_at)}</DetailRow>
                            </dl>

                            <section className="space-y-2">
                                <h3 className="text-sm font-semibold">Properties</h3>
                                <div className="rounded-lg border border-border p-3">
                                    <JsonView value={selected.properties} />
                                </div>
                            </section>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
