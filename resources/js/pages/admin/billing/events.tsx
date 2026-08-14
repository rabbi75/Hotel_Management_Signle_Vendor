import { PageHeader } from '@/components/app-shell/page-header';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, type RowAction } from '@/components/data-table/data-table-cells';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { AdminLayout } from '@/layouts/admin-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { WebhookEventRow } from '@/types/billing';
import { router } from '@inertiajs/react';
import { CircleAlert, Code, RefreshCw, SearchX, Webhook } from 'lucide-react';
import { useState } from 'react';

interface EventsPageProps {
    table: TablePayload<WebhookEventRow>;
    unprocessed: number;
    can: { replay: boolean };
}

/**
 * The gateway's side of the conversation.
 *
 * An unprocessed row is the whole point of this screen: it means the processor
 * told us something and the kit did not act on it. Without somewhere to see
 * that, a broken integration surfaces as a customer complaint weeks later.
 */
export default function AdminBillingEventsPage({ table, unprocessed, can }: EventsPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Platform' }, { label: 'Gateway events' }];
    const [inspecting, setInspecting] = useState<WebhookEventRow | null>(null);
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<WebhookEventRow> = {
        type: (row) => <span className="font-mono text-xs text-foreground">{row.type}</span>,
        gateway: (row) => <Badge variant="outline">{row.gateway}</Badge>,
        event_id: (row) => <span className="font-mono text-xs text-muted-foreground">{row.event_id}</span>,
        processed_at: (row) =>
            row.processed ? (
                <DateCell value={row.processed_at} />
            ) : (
                <Badge variant="destructive" className="gap-1">
                    <CircleAlert className="size-3" aria-hidden="true" />
                    Not processed
                </Badge>
            ),
        created_at: (row) => <DateCell value={row.created_at} />,
    };

    function rowActions(row: WebhookEventRow): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'inspect',
                label: 'Inspect payload',
                icon: <Code className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => setInspecting(row),
            },
        ];

        if (can.replay) {
            actions.push({
                id: 'replay',
                label: 'Replay',
                separatorBefore: true,
                icon: <RefreshCw className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.post(route('admin.billing.events.replay', row.id), {}, { preserveScroll: true }),
            });
        }

        return actions;
    }

    return (
        <AdminLayout title="Gateway events" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Gateway events"
                    description="Everything the payment processor has sent, and whether the application acted on it."
                />

                {unprocessed > 0 && (
                    <Alert variant="destructive">
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>
                            {unprocessed} event{unprocessed === 1 ? '' : 's'} arrived but were never processed
                        </AlertTitle>
                        <AlertDescription>
                            Inspect the payload to see what was missed, then replay it. Replaying is safe to repeat — a record already
                            in the target state is left alone.
                        </AlertDescription>
                    </Alert>
                )}

                <DataTable<WebhookEventRow>
                    payload={table}
                    propKey="table"
                    name="events"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search by event type or id…"
                    caption="Gateway webhook events"
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : Webhook}
                            title={filtered ? 'No events match those filters' : 'No events received yet'}
                            description={
                                filtered
                                    ? 'Try clearing the search or filters.'
                                    : 'Events appear here once the processor starts sending webhooks.'
                            }
                            className="border-0"
                        />
                    }
                />
            </div>

            <Sheet open={inspecting !== null} onOpenChange={(open) => !open && setInspecting(null)}>
                <SheetContent side="right" className="w-full sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle className="font-mono text-sm">{inspecting?.type}</SheetTitle>
                        <SheetDescription>
                            {inspecting?.gateway} · {inspecting?.event_id}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="mt-4 px-4 pb-4">
                        {can.replay && inspecting && (
                            <Button
                                type="button"
                                variant="outline"
                                className="mb-4 w-full"
                                onClick={() => {
                                    router.post(route('admin.billing.events.replay', inspecting.id), {}, { preserveScroll: true });
                                    setInspecting(null);
                                }}
                            >
                                <RefreshCw className="size-4" aria-hidden="true" />
                                Replay this event
                            </Button>
                        )}

                        <pre className="max-h-[70svh] overflow-auto rounded-lg border border-border bg-muted p-4 text-xs">
                            {JSON.stringify(inspecting?.payload ?? {}, null, 2)}
                        </pre>
                    </div>
                </SheetContent>
            </Sheet>
        </AdminLayout>
    );
}
