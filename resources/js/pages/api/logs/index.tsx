import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell } from '@/components/data-table/data-table-cells';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ApiLogsPageProps, ApiRequestLogRow } from '@/types/api';
import { format, isValid, parseISO } from 'date-fns';
import { EyeOff, Info, ScrollText, SearchX } from 'lucide-react';
import { useState, type ReactNode } from 'react';

const REDACTED = '[redacted]';

function statusVariant(status: number): NonNullable<BadgeProps['variant']> {
    if (status >= 500) {
        return 'destructive';
    }

    if (status >= 400) {
        return 'warning';
    }

    return 'success';
}

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PPpp') : '—';
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Renders a logged payload, marking every redacted value as such.
 *
 * A stripped credential must look deliberately removed: rendering it as an
 * empty string would read as "the client sent nothing", which is a different
 * and misleading fact.
 */
function PayloadView({ value, level = 0 }: { value: unknown; level?: number }): ReactNode {
    if (value === REDACTED) {
        return (
            <Badge variant="outline" className="gap-1">
                <EyeOff aria-hidden="true" />
                redacted
            </Badge>
        );
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return <span className="text-sm text-muted-foreground">Empty list</span>;
        }

        return (
            <ol className={level > 0 ? 'space-y-2 border-l border-border pl-3' : 'space-y-2'}>
                {value.map((entry, index) => (
                    <li key={index} className="min-w-0 space-y-1">
                        <span className="text-xs text-muted-foreground">#{index + 1}</span>
                        <PayloadView value={entry} level={level + 1} />
                    </li>
                ))}
            </ol>
        );
    }

    if (isRecord(value)) {
        const entries = Object.entries(value);

        if (entries.length === 0) {
            return <span className="text-sm text-muted-foreground">Nothing recorded</span>;
        }

        return (
            <dl className={level > 0 ? 'space-y-2 border-l border-border pl-3' : 'space-y-2'}>
                {entries.map(([key, entry]) => (
                    <div key={key} className="min-w-0">
                        <dt className="font-mono text-xs break-all text-muted-foreground">{key}</dt>
                        <dd className="min-w-0">
                            <PayloadView value={entry} level={level + 1} />
                        </dd>
                    </div>
                ))}
            </dl>
        );
    }

    if (value === null || value === undefined) {
        return <span className="text-sm text-muted-foreground">null</span>;
    }

    return <span className="text-sm break-words">{String(value)}</span>;
}

export default function ApiLogsIndex({ table, retention_days, bodies_logged }: ApiLogsPageProps) {
    const [selected, setSelected] = useState<ApiRequestLogRow | null>(null);

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<ApiRequestLogRow> = {
        method: (row) => (
            <Badge variant="outline" className="font-mono">
                {row.method}
            </Badge>
        ),
        path: (row) => <code className="block truncate font-mono text-xs">{row.path}</code>,
        status: (row) => <Badge variant={statusVariant(row.status)}>{row.status}</Badge>,
        duration_ms: (row) => <span className="tabular-nums">{row.duration_ms} ms</span>,
        user: (row) => <TextCell value={row.user} muted />,
        token: (row) => <TextCell value={row.token} muted />,
        ip_address: (row) => <TextCell value={row.ip_address} muted className="font-mono text-xs" />,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Developer' },
        { label: 'API logs' },
    ];

    return (
        <AppLayout title="API logs" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="API logs" description={`Every request to the public API, kept for ${retention_days} days.`} />

                <Alert>
                    <Info aria-hidden="true" />
                    <AlertDescription>
                        {bodies_logged
                            ? 'Request and response bodies are being retained. Credentials are stripped at any nesting depth before storage.'
                            : 'Bodies are not retained on this installation. Only request metadata is stored.'}
                    </AlertDescription>
                </Alert>

                <DataTable<ApiRequestLogRow>
                    payload={table}
                    propKey="table"
                    name="logs"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    onRowClick={(row) => setSelected(row)}
                    searchPlaceholder="Search by path…"
                    caption="Public API request log"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No requests match these filters"
                                description="Widen the date range or clear the outcome filter."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={ScrollText}
                                title="No API requests yet"
                                description="Requests appear here as soon as an integration calls the public API."
                                className="border-0"
                            />
                        )
                    }
                />
            </div>

            <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>
                            {selected?.method} {selected?.path}
                        </SheetTitle>
                        <SheetDescription>{selected?.route_name ?? 'Unnamed route'}</SheetDescription>
                    </SheetHeader>

                    {selected && (
                        <div className="space-y-5 px-4 pb-6">
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">Status</dt>
                                    <dd>
                                        <Badge variant={statusVariant(selected.status)}>{selected.status}</Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Duration</dt>
                                    <dd className="tabular-nums">{selected.duration_ms} ms</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">User</dt>
                                    <dd>{selected.user ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Token</dt>
                                    <dd>{selected.token ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">IP address</dt>
                                    <dd className="font-mono text-xs">{selected.ip_address ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">When</dt>
                                    <dd>{absolute(selected.created_at)}</dd>
                                </div>
                            </dl>

                            <div className="min-w-0">
                                <p className="text-sm text-muted-foreground">User agent</p>
                                <p className="text-sm break-words">{selected.user_agent ?? '—'}</p>
                            </div>

                            <section className="space-y-2">
                                <h3 className="text-sm font-medium">Request</h3>
                                {selected.request_body ? (
                                    <PayloadView value={selected.request_body} />
                                ) : (
                                    <p className="text-sm text-muted-foreground">Bodies are not retained for this request.</p>
                                )}
                            </section>

                            <section className="space-y-2">
                                <h3 className="text-sm font-medium">Response</h3>
                                {selected.response_body ? (
                                    <PayloadView value={selected.response_body} />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No response body stored. Successful reads are never retained.
                                    </p>
                                )}
                            </section>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
