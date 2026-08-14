import { Icon } from '@/components/app-shell/icon';
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { AreaChart } from '@/components/charts/area-chart';
import { BarChart } from '@/components/charts/bar-chart';
import { StatCard } from '@/components/charts/stat-card';
import { RelativeDateCell } from '@/components/data-table/data-table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    ActivityEntry,
    DashboardPageProps,
    DashboardWidget,
    LoginEntry,
    QuickActionsData,
    RecentActivityData,
    RecentLoginsData,
    RevenueChartData,
    SeriesPoint,
    StatsOverviewData,
    TaskEntry,
    TasksData,
    UserGrowthData,
    WidgetLayoutEntry,
    WidgetSize,
} from '@/types/dashboard';
import { DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, rectSortingStrategy, sortableKeyboardCoordinates, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link, router } from '@inertiajs/react';
import { BarChart3, CircleCheck, GripVertical, Inbox, KeyRound, LayoutDashboard, RotateCcw, ScrollText, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

const SPAN: Record<WidgetSize, string> = {
    sm: 'md:col-span-1 xl:col-span-3',
    md: 'md:col-span-2 xl:col-span-6',
    lg: 'md:col-span-2 xl:col-span-8',
    full: 'md:col-span-2 xl:col-span-12',
};

const PRIORITY_VARIANT = {
    high: 'destructive',
    medium: 'warning',
    low: 'secondary',
} as const;

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function records(value: unknown): Record<string, unknown>[] {
    return (Array.isArray(value) ? value : []).filter(isRecord);
}

function text(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value : null;
}

function identifier(value: unknown, fallback: number): number | string {
    return typeof value === 'number' || typeof value === 'string' ? value : fallback;
}

function asSeries(value: unknown): SeriesPoint[] {
    return records(value).map((point) => ({
        bucket: String(point.bucket ?? ''),
        label: String(point.label ?? point.bucket ?? ''),
        value: Number(point.value ?? 0),
        cumulative: Number(point.cumulative ?? 0),
    }));
}

function statsOverview(data: Record<string, unknown>): StatsOverviewData {
    return {
        period_days: Number(data.period_days ?? 0),
        stats: records(data.stats).map((stat) => ({
            key: String(stat.key ?? ''),
            label: String(stat.label ?? ''),
            value: Number(stat.value ?? 0),
            previous: stat.previous === null || stat.previous === undefined ? null : Number(stat.previous),
            delta: stat.delta === null || stat.delta === undefined ? null : Number(stat.delta),
            direction: stat.direction === 'up' || stat.direction === 'down' ? stat.direction : 'flat',
            icon: String(stat.icon ?? 'activity'),
        })),
    };
}

function revenueChart(data: Record<string, unknown>): RevenueChartData {
    return {
        available: data.available === true,
        reason: text(data.reason),
        currency: String(data.currency ?? 'USD'),
        series: asSeries(data.series),
        total: Number(data.total ?? 0),
    };
}

function userGrowth(data: Record<string, unknown>): UserGrowthData {
    return {
        series: asSeries(data.series),
        total: Number(data.total ?? 0),
        cumulative: Number(data.cumulative ?? 0),
    };
}

function recentActivity(data: Record<string, unknown>): RecentActivityData {
    return {
        items: records(data.items).map((item, index): ActivityEntry => ({
            id: identifier(item.id, index),
            log_name: text(item.log_name),
            description: String(item.description ?? ''),
            subject: text(item.subject),
            causer: text(item.causer),
            created_at: text(item.created_at),
        })),
    };
}

function recentLogins(data: Record<string, unknown>): RecentLoginsData {
    return {
        failed_recently: Number(data.failed_recently ?? 0),
        items: records(data.items).map((item, index): LoginEntry => ({
            id: identifier(item.id, index),
            user: text(item.user),
            email: text(item.email),
            ip_address: text(item.ip_address),
            browser: text(item.browser),
            platform: text(item.platform),
            successful: item.successful === true,
            failure_reason: text(item.failure_reason),
            logged_in_at: String(item.logged_in_at ?? ''),
        })),
    };
}

function tasks(data: Record<string, unknown>): TasksData {
    return {
        completed: Number(data.completed ?? 0),
        total: Number(data.total ?? 0),
        items: records(data.items).map((item, index): TaskEntry => ({
            key: String(item.key ?? index),
            label: String(item.label ?? ''),
            icon: text(item.icon),
            url: text(item.url),
            priority: item.priority === 'high' || item.priority === 'medium' ? item.priority : 'low',
        })),
    };
}

function quickActions(data: Record<string, unknown>): QuickActionsData {
    return {
        actions: records(data.actions)
            .map((action, index) => ({
                key: String(action.key ?? index),
                label: String(action.label ?? ''),
                icon: text(action.icon),
                url: text(action.url),
            }))
            .filter((action): action is QuickActionsData['actions'][number] => action.url !== null),
    };
}

interface EchoLike {
    private(channel: string): { listen(event: string, handler: () => void): unknown };
    leave(channel: string): void;
}

export default function Dashboard({ widgets, can, channel }: DashboardPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard' }];
    const layoutUrl = routeUrl('dashboard.layout.update');
    const resetUrl = routeUrl('dashboard.layout.destroy');
    const reorderable = can.customize && layoutUrl !== null;

    const byKey = useMemo(() => new Map(widgets.map((widget) => [widget.key, widget])), [widgets]);
    const serverOrder = useMemo(() => widgets.map((widget) => widget.key), [widgets]);
    const [order, setOrder] = useState<string[]>(serverOrder);

    useEffect(() => {
        setOrder(serverOrder);
    }, [serverOrder]);

    // The event carries no figures — it only says the numbers are stale — so the
    // client re-fetches through the normal, authorised path.
    useEffect(() => {
        const echo = (window as unknown as { Echo?: EchoLike }).Echo;

        if (!channel || !echo) {
            return;
        }

        echo.private(channel).listen('.dashboard.stats.updated', () => {
            router.reload({ only: ['widgets'] });
        });

        return () => echo.leave(channel);
    }, [channel]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const persist = useCallback(
        (next: string[]) => {
            if (!layoutUrl) {
                return;
            }

            const layout: WidgetLayoutEntry[] = next
                .map((key) => byKey.get(key))
                .filter((widget): widget is DashboardWidget => widget !== undefined)
                .map((widget) => ({ key: widget.key, size: widget.size }));

            router.put(layoutUrl, { layout }, { preserveScroll: true, preserveState: true });
        },
        [byKey, layoutUrl],
    );

    function onDragEnd(event: DragEndEvent): void {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        setOrder((current) => {
            const from = current.indexOf(String(active.id));
            const to = current.indexOf(String(over.id));

            if (from === -1 || to === -1) {
                return current;
            }

            const next = arrayMove(current, from, to);
            persist(next);

            return next;
        });
    }

    const visible = order.map((key) => byKey.get(key)).filter((widget): widget is DashboardWidget => widget !== undefined);

    const grid = (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-12">
            {visible.map((widget) => (
                <WidgetFrame key={widget.key} widget={widget} sortable={reorderable} />
            ))}
        </div>
    );

    return (
        <AppLayout title="Dashboard" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Dashboard"
                    description={
                        reorderable
                            ? 'An overview of this workspace. Drag a widget by its handle — or focus the handle and use the arrow keys — to rearrange.'
                            : 'An overview of this workspace.'
                    }
                    actions={
                        reorderable && resetUrl ? (
                            <Button variant="outline" onClick={() => router.delete(resetUrl, { preserveScroll: true })}>
                                <RotateCcw className="size-4" aria-hidden="true" />
                                Reset layout
                            </Button>
                        ) : null
                    }
                />

                {visible.length === 0 ? (
                    <EmptyState
                        icon={LayoutDashboard}
                        title="No widgets to show"
                        description="You do not have access to any dashboard widget in this workspace yet."
                    />
                ) : reorderable ? (
                    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                        <SortableContext items={order} strategy={rectSortingStrategy}>
                            {grid}
                        </SortableContext>
                    </DndContext>
                ) : (
                    grid
                )}
            </div>
        </AppLayout>
    );
}

function WidgetFrame({ widget, sortable }: { widget: DashboardWidget; sortable: boolean }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: widget.key, disabled: !sortable });

    return (
        <section
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(SPAN[widget.size] ?? SPAN.md, isDragging && 'z-10 opacity-80')}
            aria-label={widget.title}
        >
            <Card className="h-full">
                <CardHeader className="flex flex-row items-start justify-between gap-2">
                    <div className="min-w-0">
                        <CardTitle className="truncate">{widget.title}</CardTitle>
                        {widget.description && <p className="mt-0.5 text-sm text-muted-foreground">{widget.description}</p>}
                    </div>

                    {sortable && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            className="cursor-grab touch-none active:cursor-grabbing"
                            aria-label={`Reorder ${widget.title}`}
                            {...attributes}
                            {...listeners}
                        >
                            <GripVertical className="size-4" aria-hidden="true" />
                        </Button>
                    )}
                </CardHeader>

                <CardContent className="pb-6">
                    <WidgetBody widget={widget} />
                </CardContent>
            </Card>
        </section>
    );
}

function WidgetBody({ widget }: { widget: DashboardWidget }) {
    const data = widget.data;

    // A widget whose payload has not arrived — a deferred or in-flight partial
    // reload — shows its own skeleton rather than an empty shell.
    if (!isRecord(data)) {
        return <WidgetSkeleton size={widget.size} />;
    }

    switch (widget.key) {
        case 'stats-overview': {
            const { stats, period_days: periodDays } = statsOverview(data);

            if (stats.length === 0) {
                return (
                    <EmptyState
                        icon={BarChart3}
                        title="Nothing to summarise"
                        description="Counters appear once this workspace has members."
                    />
                );
            }

            return (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {stats.map((stat) => (
                        <StatCard
                            key={stat.key}
                            label={stat.label}
                            value={new Intl.NumberFormat().format(stat.value)}
                            delta={stat.delta}
                            deltaLabel={periodDays > 0 ? `vs previous ${periodDays} days` : undefined}
                            icon={<Icon name={stat.icon} />}
                        />
                    ))}
                </div>
            );
        }

        case 'revenue-chart': {
            const revenue = revenueChart(data);

            if (!revenue.available) {
                return (
                    <EmptyState
                        icon={BarChart3}
                        title="Revenue is not available"
                        description={revenue.reason ?? 'Billing is not enabled.'}
                    />
                );
            }

            const money = (value: number | string): string =>
                new Intl.NumberFormat(undefined, { style: 'currency', currency: revenue.currency }).format(Number(value));

            return (
                <AreaChart
                    data={revenue.series}
                    xKey="label"
                    series={[{ key: 'value', label: 'Revenue' }]}
                    valueFormatter={money}
                    summary={`Monthly revenue, totalling ${money(revenue.total)} over the period.`}
                    empty={<EmptyState icon={BarChart3} title="No revenue in this period" description="Paid invoices will appear here." />}
                />
            );
        }

        case 'user-growth-chart': {
            const growth = userGrowth(data);

            return (
                <div className="space-y-3">
                    <BarChart
                        data={growth.series}
                        xKey="label"
                        series={[{ key: 'value', label: 'New members' }]}
                        summary={`New members per month; ${growth.total} joined in this period, ${growth.cumulative} in total.`}
                        empty={<EmptyState icon={BarChart3} title="No sign-ups yet" description="Growth appears once people join." />}
                    />
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground tabular-nums">{growth.total}</span> joined in this period ·{' '}
                        <span className="font-medium text-foreground tabular-nums">{growth.cumulative}</span> members in total
                    </p>
                </div>
            );
        }

        case 'recent-activity': {
            const { items } = recentActivity(data);

            if (items.length === 0) {
                return (
                    <EmptyState icon={ScrollText} title="No recent activity" description="Changes to workspace data will show up here." />
                );
            }

            return (
                <ul className="divide-y divide-border">
                    {items.map((item) => (
                        <li key={item.id} className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                            <div className="min-w-0">
                                <p className="truncate text-sm">{item.description}</p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {item.causer ?? 'System'}
                                    {item.subject && <> · {item.subject}</>}
                                </p>
                            </div>
                            <RelativeDateCell value={item.created_at} />
                        </li>
                    ))}
                </ul>
            );
        }

        case 'recent-logins': {
            const { items, failed_recently: failedRecently } = recentLogins(data);

            if (items.length === 0) {
                return <EmptyState icon={KeyRound} title="No recent sign-ins" description="Authentication attempts will appear here." />;
            }

            return (
                <div className="space-y-3">
                    {failedRecently > 0 && (
                        <p className="flex items-center gap-2 rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-sm text-warning">
                            <TriangleAlert className="size-4 shrink-0" aria-hidden="true" />
                            {failedRecently} failed attempt{failedRecently === 1 ? '' : 's'} in the last 24 hours.
                        </p>
                    )}

                    <ul className="divide-y divide-border">
                        {items.map((item) => (
                            <li key={item.id} className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{item.user ?? item.email ?? 'Unknown'}</p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {item.browser ?? 'Unknown browser'}
                                        {item.ip_address && <span className="font-mono"> · {item.ip_address}</span>}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-3">
                                    <Badge variant={item.successful ? 'success' : 'destructive'}>
                                        {item.successful ? 'Success' : 'Failed'}
                                    </Badge>
                                    <RelativeDateCell value={item.logged_in_at} />
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            );
        }

        case 'tasks': {
            const { items } = tasks(data);

            if (items.length === 0) {
                return (
                    <EmptyState icon={CircleCheck} title="Nothing outstanding" description="Your account and workspace are fully set up." />
                );
            }

            return (
                <ul className="space-y-2">
                    {items.map((item) => (
                        <li key={item.key} className="flex items-center justify-between gap-3 rounded-md border border-border p-3">
                            <span className="flex min-w-0 items-center gap-2">
                                <Icon name={item.icon} className="text-muted-foreground" />
                                <span className="truncate text-sm">{item.label}</span>
                            </span>
                            <span className="flex shrink-0 items-center gap-2">
                                <Badge variant={PRIORITY_VARIANT[item.priority]}>{item.priority}</Badge>
                                {item.url && (
                                    <Button asChild size="xs" variant="ghost">
                                        <Link href={item.url}>Fix</Link>
                                    </Button>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            );
        }

        case 'quick-actions': {
            const { actions } = quickActions(data);

            if (actions.length === 0) {
                return (
                    <EmptyState
                        icon={Inbox}
                        title="No shortcuts available"
                        description="Actions you are allowed to take will appear here."
                    />
                );
            }

            return (
                <div className="flex flex-wrap gap-2">
                    {actions.map((action) => (
                        <Button key={action.key} asChild variant="outline" size="sm">
                            <Link href={action.url}>
                                <Icon name={action.icon} />
                                {action.label}
                            </Link>
                        </Button>
                    ))}
                </div>
            );
        }

        default:
            return (
                <EmptyState
                    icon={LayoutDashboard}
                    title="This widget has no renderer yet"
                    description={`“${widget.key}” is registered on the server but the interface does not know how to draw it.`}
                />
            );
    }
}

function WidgetSkeleton({ size }: { size: WidgetSize }) {
    if (size === 'full') {
        return (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true">
                {[0, 1, 2, 3].map((index) => (
                    <div key={index} className="space-y-3 rounded-lg border border-border p-5">
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-8 w-20" />
                        <Skeleton className="h-4 w-28" />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-3" aria-busy="true">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="h-40 w-full" />
            <Skeleton className="h-4 w-24" />
        </div>
    );
}
