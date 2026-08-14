import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CreatedWebhookSecret, WebhookDeliveryRow, WebhookEndpointRow, WebhooksPageProps } from '@/types/api';
import { router, useForm } from '@inertiajs/react';
import { Ban, Plus, RefreshCw, SearchX, Trash2, TriangleAlert, Webhook } from 'lucide-react';
import { useEffect, useId, useMemo, useState, type FormEvent } from 'react';
import { CopyButton } from '../copy-button';

interface Props extends WebhooksPageProps {
    /** Present on exactly one render, immediately after creation. */
    created_secret: CreatedWebhookSecret | null;
}

interface EndpointForm {
    url: string;
    description: string;
    events: string[];
    is_active: boolean;
}

const STATUS_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    success: 'success',
    destructive: 'destructive',
    warning: 'warning',
    muted: 'secondary',
};

export default function WebhooksIndex({ endpoints, deliveries, events, signature_header, timestamp_header, can, created_secret }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const urlId = useId();
    const descriptionId = useId();

    const [createOpen, setCreateOpen] = useState(false);
    const [revealed, setRevealed] = useState<CreatedWebhookSecret | null>(null);
    const [inspected, setInspected] = useState<WebhookDeliveryRow | null>(null);

    const mayManage = can.manage && allows('api.webhooks.manage');
    const filtered = Boolean(deliveries.state.search) || Object.keys(deliveries.state.filters).length > 0;

    const grouped = useMemo(() => {
        const map = new Map<string, typeof events>();

        for (const event of events) {
            map.set(event.group, [...(map.get(event.group) ?? []), event]);
        }

        return [...map.entries()];
    }, [events]);

    const form = useForm<EndpointForm>({
        url: '',
        description: '',
        events: [],
        is_active: true,
    });

    useEffect(() => {
        if (created_secret) {
            setRevealed(created_secret);
            setCreateOpen(false);
        }
    }, [created_secret]);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.post(route('api.webhooks.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function toggleEvent(name: string, checked: boolean): void {
        form.setData('events', checked ? [...form.data.events, name] : form.data.events.filter((entry) => entry !== name));
    }

    function toggleActive(endpoint: WebhookEndpointRow, active: boolean): void {
        router.patch(route('api.webhooks.update', endpoint.id), { is_active: active }, { preserveScroll: true });
    }

    async function remove(endpoint: WebhookEndpointRow): Promise<void> {
        const ok = await confirm({
            title: 'Delete this webhook endpoint?',
            description: `${endpoint.url} stops receiving deliveries immediately, and its history is removed.`,
            variant: 'destructive',
            confirmLabel: 'Delete endpoint',
            confirmWord: 'delete',
        });

        if (ok) {
            router.delete(route('api.webhooks.destroy', endpoint.id), { preserveScroll: true });
        }
    }

    function redeliver(delivery: WebhookDeliveryRow): void {
        router.post(route('api.webhooks.deliveries.redeliver', delivery.id), {}, { preserveScroll: true });
    }

    const columns: ColumnRenderers<WebhookDeliveryRow> = {
        event: (row) => <code className="font-mono text-xs">{row.event}</code>,
        endpoint_url: (row) => <TextCell value={row.endpoint_url} muted className="max-w-[18rem]" />,
        status: (row) => <Badge variant={STATUS_VARIANT[row.status_color] ?? 'secondary'}>{row.status_label}</Badge>,
        status_code: (row) => <TextCell value={row.status_code ?? '—'} className="tabular-nums" />,
        attempt: (row) => <span className="tabular-nums">{row.attempt}</span>,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
    };

    function rowActions(row: WebhookDeliveryRow): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'inspect',
                label: 'Inspect',
                onSelect: () => setInspected(row),
            },
        ];

        if (mayManage) {
            actions.push({
                id: 'redeliver',
                label: 'Redeliver',
                icon: <RefreshCw className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => redeliver(row),
            });
        }

        return actions;
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Developer' },
        { label: 'Webhooks' },
    ];

    return (
        <AppLayout title="Webhooks" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Webhooks"
                    description="Outbound notifications, signed so a receiver can prove they came from here."
                    actions={
                        mayManage ? (
                            <Button type="button" onClick={() => setCreateOpen(true)}>
                                <Plus className="size-4" aria-hidden="true" />
                                New endpoint
                            </Button>
                        ) : null
                    }
                />

                <Tabs defaultValue="endpoints" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="endpoints">Endpoints</TabsTrigger>
                        <TabsTrigger value="deliveries">Delivery history</TabsTrigger>
                        <TabsTrigger value="verifying">Verifying</TabsTrigger>
                    </TabsList>

                    <TabsContent value="endpoints">
                        {endpoints.length === 0 ? (
                            <Card>
                                <CardContent className="py-4">
                                    <EmptyState
                                        icon={Webhook}
                                        title="No endpoints yet"
                                        description="Add an HTTPS URL and choose the events it should receive."
                                        action={
                                            mayManage ? (
                                                <Button type="button" size="sm" onClick={() => setCreateOpen(true)}>
                                                    <Plus className="size-4" aria-hidden="true" />
                                                    New endpoint
                                                </Button>
                                            ) : null
                                        }
                                    />
                                </CardContent>
                            </Card>
                        ) : (
                            <ul className="grid gap-4">
                                {endpoints.map((endpoint) => (
                                    <li key={endpoint.id}>
                                        <Card>
                                            <CardHeader>
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                    <div className="min-w-0 space-y-1">
                                                        <CardTitle className="font-mono text-sm break-all">{endpoint.url}</CardTitle>
                                                        <CardDescription>{endpoint.description ?? 'No description'}</CardDescription>
                                                    </div>

                                                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                                                        {endpoint.disabled_at ? (
                                                            <Badge variant="destructive">
                                                                <Ban aria-hidden="true" />
                                                                Auto-disabled
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant={endpoint.is_active ? 'success' : 'secondary'}>
                                                                {endpoint.is_active ? 'Active' : 'Paused'}
                                                            </Badge>
                                                        )}

                                                        {mayManage && (
                                                            <>
                                                                <Switch
                                                                    checked={endpoint.is_active}
                                                                    aria-label={`${endpoint.is_active ? 'Pause' : 'Resume'} ${endpoint.url}`}
                                                                    onCheckedChange={(checked) => toggleActive(endpoint, checked)}
                                                                />
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon-sm"
                                                                    aria-label={`Delete ${endpoint.url}`}
                                                                    onClick={() => void remove(endpoint)}
                                                                >
                                                                    <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </CardHeader>

                                            <CardContent className="space-y-3">
                                                {endpoint.disabled_at && (
                                                    <Alert variant="destructive">
                                                        <TriangleAlert aria-hidden="true" />
                                                        <AlertTitle>Disabled after repeated failures</AlertTitle>
                                                        <AlertDescription>
                                                            {endpoint.failure_count} consecutive deliveries failed. Fix the receiver, then
                                                            re-enable the endpoint to reset the counter.
                                                        </AlertDescription>
                                                    </Alert>
                                                )}

                                                <div className="flex flex-wrap gap-1">
                                                    {endpoint.events.map((event) => (
                                                        <Badge key={event} variant="outline">
                                                            {event}
                                                        </Badge>
                                                    ))}
                                                </div>

                                                <dl className="grid gap-1 text-xs text-muted-foreground sm:grid-cols-3">
                                                    <div>
                                                        <dt className="inline">Secret: </dt>
                                                        <dd className="inline font-mono">{endpoint.secret_hint}</dd>
                                                    </div>
                                                    <div>
                                                        <dt className="inline">Failures: </dt>
                                                        <dd className="inline tabular-nums">{endpoint.failure_count}</dd>
                                                    </div>
                                                    <div>
                                                        <dt className="inline">Last success: </dt>
                                                        <dd className="inline">
                                                            {endpoint.last_success_at ? (
                                                                <RelativeDateCell value={endpoint.last_success_at} />
                                                            ) : (
                                                                'never'
                                                            )}
                                                        </dd>
                                                    </div>
                                                </dl>
                                            </CardContent>
                                        </Card>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </TabsContent>

                    <TabsContent value="deliveries">
                        <DataTable<WebhookDeliveryRow>
                            payload={deliveries}
                            propKey="deliveries"
                            name="deliveries"
                            columns={columns}
                            getRowId={(row) => String(row.id)}
                            rowActions={rowActions}
                            onRowClick={(row) => setInspected(row)}
                            searchPlaceholder="Search by event…"
                            caption="Webhook delivery attempts"
                            emptyState={
                                filtered ? (
                                    <EmptyState
                                        icon={SearchX}
                                        title="No deliveries match these filters"
                                        description="Clear the status filter to see every attempt."
                                        className="border-0"
                                    />
                                ) : (
                                    <EmptyState
                                        icon={Webhook}
                                        title="No deliveries yet"
                                        description="Attempts appear here as soon as a subscribed event fires."
                                        className="border-0"
                                    />
                                )
                            }
                        />
                    </TabsContent>

                    <TabsContent value="verifying">
                        <Card>
                            <CardHeader>
                                <CardTitle>Verifying a delivery</CardTitle>
                                <CardDescription>
                                    Compute an HMAC-SHA256 over the timestamp, a dot, and the raw request body — then compare it in constant
                                    time.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">Signature header</dt>
                                        <dd className="font-mono text-xs">{signature_header}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Timestamp header</dt>
                                        <dd className="font-mono text-xs">{timestamp_header}</dd>
                                    </div>
                                </dl>

                                <div className="overflow-x-auto rounded-md border border-border bg-muted/40 p-3">
                                    <pre className="font-mono text-xs">
                                        <code>{`expected = "v1=" + hmac_sha256(secret, timestamp + "." + raw_body)\nreject if abs(now - timestamp) > 300`}</code>
                                    </pre>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    The timestamp is part of the signed material, so a captured delivery cannot be replayed once the
                                    tolerance window has passed.
                                </p>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>New webhook endpoint</DialogTitle>
                        <DialogDescription>
                            HTTPS only. Addresses that resolve to a private, loopback or metadata range are rejected.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} noValidate className="space-y-5">
                        <div className="grid gap-2">
                            <Label htmlFor={urlId}>Endpoint URL</Label>
                            <Input
                                id={urlId}
                                type="url"
                                inputMode="url"
                                value={form.data.url}
                                placeholder="https://example.com/webhooks/inbound"
                                required
                                aria-invalid={Boolean(form.errors.url)}
                                aria-describedby={form.errors.url ? `${urlId}-error` : undefined}
                                onChange={(event) => form.setData('url', event.target.value)}
                            />
                            {form.errors.url && (
                                <p id={`${urlId}-error`} className="text-sm font-medium text-destructive">
                                    {form.errors.url}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={descriptionId}>Description</Label>
                            <Input
                                id={descriptionId}
                                value={form.data.description}
                                placeholder="Billing sync"
                                onChange={(event) => form.setData('description', event.target.value)}
                            />
                        </div>

                        <fieldset className="grid gap-4">
                            <legend className="text-sm font-medium">Events</legend>

                            {grouped.map(([group, groupEvents]) => (
                                <div key={group} className="grid gap-2">
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{group}</p>

                                    {groupEvents.map((event) => (
                                        <label key={event.name} className="flex items-start gap-3 text-sm">
                                            <Checkbox
                                                checked={form.data.events.includes(event.name)}
                                                onCheckedChange={(checked) => toggleEvent(event.name, checked === true)}
                                            />
                                            <span className="min-w-0">
                                                <span className="block font-mono text-xs">{event.name}</span>
                                                <span className="block text-xs text-muted-foreground">{event.description}</span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            ))}

                            {form.errors.events && <p className="text-sm font-medium text-destructive">{form.errors.events}</p>}
                        </fieldset>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner className="size-4" aria-hidden="true" />}
                                Create endpoint
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={revealed !== null} onOpenChange={(open) => !open && setRevealed(null)}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Copy your signing secret now</DialogTitle>
                        <DialogDescription>Use it to verify the signature on every delivery.</DialogDescription>
                    </DialogHeader>

                    <Alert variant="warning">
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>You will not see this again</AlertTitle>
                        <AlertDescription>The secret is encrypted at rest and never returned to the browser afterwards.</AlertDescription>
                    </Alert>

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <Input
                            readOnly
                            aria-label="Signing secret"
                            value={revealed?.secret ?? ''}
                            className="font-mono text-xs"
                            onFocus={(event) => event.currentTarget.select()}
                        />
                        <CopyButton value={revealed?.secret ?? ''} label="Copy secret" className="shrink-0" />
                    </div>

                    <DialogFooter>
                        <Button type="button" onClick={() => setRevealed(null)}>
                            I have stored it
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Sheet open={inspected !== null} onOpenChange={(open) => !open && setInspected(null)}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>Delivery {inspected?.id}</SheetTitle>
                        <SheetDescription>
                            {inspected?.event} → {inspected?.endpoint_url ?? 'endpoint removed'}
                        </SheetDescription>
                    </SheetHeader>

                    {inspected && (
                        <div className="space-y-5 px-4 pb-6">
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">Status</dt>
                                    <dd>
                                        <Badge variant={STATUS_VARIANT[inspected.status_color] ?? 'secondary'}>
                                            {inspected.status_label}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Response code</dt>
                                    <dd className="tabular-nums">{inspected.status_code ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Attempt</dt>
                                    <dd className="tabular-nums">{inspected.attempt}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Duration</dt>
                                    <dd className="tabular-nums">{inspected.duration_ms ?? '—'} ms</dd>
                                </div>
                            </dl>

                            {inspected.error && (
                                <Alert variant="destructive">
                                    <TriangleAlert aria-hidden="true" />
                                    <AlertTitle>Delivery failed</AlertTitle>
                                    <AlertDescription className="break-words">{inspected.error}</AlertDescription>
                                </Alert>
                            )}

                            <section className="space-y-2">
                                <h3 className="text-sm font-medium">Request payload</h3>
                                <div className="max-h-72 overflow-auto rounded-md border border-border bg-muted/40 p-3">
                                    <pre className="font-mono text-xs whitespace-pre-wrap">
                                        <code>{JSON.stringify(inspected.payload, null, 2)}</code>
                                    </pre>
                                </div>
                            </section>

                            <section className="space-y-2">
                                <h3 className="text-sm font-medium">Response body</h3>
                                <div className="max-h-72 overflow-auto rounded-md border border-border bg-muted/40 p-3">
                                    <pre className="font-mono text-xs whitespace-pre-wrap">
                                        <code>{inspected.response_body ?? 'No response body recorded.'}</code>
                                    </pre>
                                </div>
                            </section>

                            {mayManage && (
                                <Button type="button" variant="secondary" onClick={() => redeliver(inspected)}>
                                    <RefreshCw className="size-4" aria-hidden="true" />
                                    Redeliver
                                </Button>
                            )}
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
