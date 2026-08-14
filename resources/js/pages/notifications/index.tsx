import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import type { BulkAction } from '@/components/data-table/data-table-bulk-actions';
import { RelativeDateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, NotificationItem, TablePayload } from '@/types';
import type { EnumOption } from '@/types/users';
import { Link, router, useForm } from '@inertiajs/react';
import { BellOff, CheckCheck, ExternalLink, Inbox, MailOpen, SearchX, Trash2 } from 'lucide-react';

interface NotificationPreferences {
    channels?: Record<string, boolean>;
    types?: Record<string, Record<string, boolean>>;
}

interface NotificationsIndexProps {
    table: TablePayload<NotificationItem>;
    unread: number;
    channels: EnumOption[];
    preferences: NotificationPreferences;
}

const LEVEL_VARIANT: Record<NotificationItem['level'], NonNullable<BadgeProps['variant']>> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    critical: 'destructive',
};

/** The in-app inbox is the record of what was sent, so it is never opt-out. */
const REQUIRED_CHANNEL = 'database';

export default function NotificationsIndex({ table, unread, channels, preferences }: NotificationsIndexProps) {
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Notifications' }];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<NotificationItem> = {
        title: (row) => (
            <div className={cn('min-w-0 space-y-0.5', !row.read_at && 'font-medium')}>
                <div className="flex items-center gap-2">
                    {!row.read_at && <span className="size-1.5 shrink-0 rounded-full bg-primary" aria-label="Unread" />}
                    <span className="truncate text-sm">{row.title}</span>
                </div>
                {row.body && <p className="truncate text-xs text-muted-foreground">{row.body}</p>}
            </div>
        ),
        level: (row) => <Badge variant={LEVEL_VARIANT[row.level]}>{row.level}</Badge>,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
        read_at: (row) => (row.read_at ? <RelativeDateCell value={row.read_at} /> : <TextCell value="Unread" muted />),
    };

    function rowActions(row: NotificationItem): RowAction[] {
        const actions: RowAction[] = [];

        if (row.action_url) {
            actions.push({
                id: 'open',
                label: row.action_label ?? 'Open',
                icon: <ExternalLink className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(row.action_url ?? '#'),
            });
        }

        if (!row.read_at) {
            actions.push({
                id: 'read',
                label: 'Mark as read',
                icon: <MailOpen className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.post(route('notifications.read_one', row.id), {}, { preserveScroll: true, preserveState: true }),
            });
        }

        actions.push({
            id: 'delete',
            label: 'Delete',
            destructive: true,
            separatorBefore: true,
            icon: <Trash2 className="size-4" aria-hidden="true" />,
            onSelect: async () => {
                const ok = await confirm({
                    title: 'Delete this notification?',
                    description: 'It is removed from your inbox permanently.',
                    variant: 'destructive',
                    confirmLabel: 'Delete',
                });

                if (ok) {
                    router.delete(route('notifications.destroy', row.id), { preserveScroll: true, preserveState: true });
                }
            },
        });

        return actions;
    }

    const bulkActions: BulkAction<NotificationItem>[] = [
        {
            id: 'read',
            label: 'Mark as read',
            icon: <MailOpen className="size-4" aria-hidden="true" />,
            onSelect: (rows) =>
                router.post(route('notifications.read'), { ids: rows.map((row) => row.id) }, { preserveScroll: true, preserveState: true }),
        },
        {
            id: 'delete',
            label: 'Delete',
            variant: 'destructive',
            icon: <Trash2 className="size-4" aria-hidden="true" />,
            onSelect: async (rows) => {
                const ok = await confirm({
                    title: `Delete ${rows.length} notification${rows.length === 1 ? '' : 's'}?`,
                    description: 'They are removed from your inbox permanently.',
                    variant: 'destructive',
                    confirmLabel: 'Delete',
                });

                if (ok) {
                    router.delete(route('notifications.destroy_bulk'), {
                        data: { ids: rows.map((row) => row.id) },
                        preserveScroll: true,
                        preserveState: true,
                    });
                }
            },
        },
    ];

    async function clearAll(): Promise<void> {
        const ok = await confirm({
            title: 'Delete every notification?',
            description: 'Your whole inbox is emptied. This cannot be undone.',
            variant: 'destructive',
            confirmWord: 'delete',
            confirmLabel: 'Empty inbox',
        });

        if (ok) {
            router.delete(route('notifications.destroy_all'), { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Notifications" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Notifications"
                    description={
                        <span aria-live="polite">
                            {unread === 0 ? 'You are all caught up.' : `${unread} unread notification${unread === 1 ? '' : 's'}.`}
                        </span>
                    }
                    actions={
                        <>
                            <Button
                                variant="outline"
                                disabled={unread === 0}
                                onClick={() => router.post(route('notifications.read_all'), {}, { preserveScroll: true })}
                            >
                                <CheckCheck className="size-4" aria-hidden="true" />
                                Mark all read
                            </Button>
                            <Button variant="outline" disabled={table.meta.total === 0} onClick={() => void clearAll()}>
                                <Trash2 className="size-4" aria-hidden="true" />
                                Empty inbox
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <DataTable<NotificationItem>
                        payload={table}
                        propKey="table"
                        name="notifications"
                        columns={columns}
                        getRowId={(row) => row.id}
                        selectable
                        bulkActions={bulkActions}
                        rowActions={rowActions}
                        searchPlaceholder="Search notifications…"
                        caption="Your notification inbox"
                        emptyState={
                            filtered ? (
                                <EmptyState
                                    icon={SearchX}
                                    title="Nothing matches these filters"
                                    description="Try clearing the read/unread or level filter."
                                    className="border-0"
                                />
                            ) : (
                                <EmptyState
                                    icon={Inbox}
                                    title="Your inbox is empty"
                                    description="Notifications about your workspace will appear here."
                                    className="border-0"
                                />
                            )
                        }
                    />

                    <PreferencesPanel channels={channels} preferences={preferences} />
                </div>
            </div>
        </AppLayout>
    );
}

interface PreferencesFormValues {
    channels: Record<string, boolean>;
    [key: string]: Record<string, boolean>;
}

function PreferencesPanel({ channels, preferences }: { channels: EnumOption[]; preferences: NotificationPreferences }) {
    const optional = channels.filter((channel) => channel.value !== REQUIRED_CHANNEL);

    const form = useForm<PreferencesFormValues>({
        channels: Object.fromEntries(optional.map((channel) => [channel.value, preferences.channels?.[channel.value] ?? true])),
    });

    const { data, setData, processing, isDirty, recentlySuccessful } = form;

    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Delivery preferences</CardTitle>
                <CardDescription>Choose where a notification reaches you.</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(route('notifications.preferences'), { preserveScroll: true });
                    }}
                >
                    <fieldset className="space-y-4">
                        <legend className="sr-only">Channels</legend>

                        <div className="flex items-start justify-between gap-3 opacity-70">
                            <div className="min-w-0">
                                <Label htmlFor="channel-database">In-app inbox</Label>
                                <p className="text-xs text-muted-foreground">Always on — it is the record of what was sent.</p>
                            </div>
                            <Switch id="channel-database" checked disabled aria-describedby="channel-database-note" />
                        </div>
                        <p id="channel-database-note" className="sr-only">
                            The in-app inbox cannot be turned off.
                        </p>

                        {optional.map((channel) => (
                            <div key={channel.value} className="flex items-start justify-between gap-3">
                                <Label htmlFor={`channel-${channel.value}`}>{channel.label}</Label>
                                <Switch
                                    id={`channel-${channel.value}`}
                                    checked={data.channels[channel.value] ?? true}
                                    onCheckedChange={(checked) => setData('channels', { ...data.channels, [channel.value]: checked })}
                                />
                            </div>
                        ))}
                    </fieldset>

                    {optional.length === 0 && (
                        <EmptyState
                            icon={BellOff}
                            title="No optional channels"
                            description="This installation only delivers to the in-app inbox."
                        />
                    )}

                    <div className="flex items-center justify-between gap-3">
                        <span className="text-sm text-muted-foreground" aria-live="polite">
                            {recentlySuccessful && !isDirty ? 'Saved' : isDirty ? 'Unsaved changes' : ''}
                        </span>
                        <Button type="submit" size="sm" loading={processing} disabled={!isDirty}>
                            Save
                        </Button>
                    </div>
                </form>

                <p className="mt-4 text-xs text-muted-foreground">
                    Account-wide security alerts are always sent.{' '}
                    <Link href={route('profile.show')} className="underline underline-offset-4">
                        Manage your profile
                    </Link>
                    .
                </p>
            </CardContent>
        </Card>
    );
}
