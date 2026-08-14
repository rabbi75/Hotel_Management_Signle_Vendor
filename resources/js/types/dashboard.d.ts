/*
|------------------------------------------------------------------------------
| Dashboard
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\Dashboard: WidgetSize, Widget::toArray(),
| DashboardService::for()/available() and DashboardController::__invoke().
|
| A widget's `data` is deliberately typed as an open record: the registry is
| extensible from any module, so the page narrows each payload at runtime with
| the guards in pages/dashboard/index.tsx rather than trusting a cast.
|
*/

export type WidgetSize = 'sm' | 'md' | 'lg' | 'full';

/** `Widget::toArray()` — metadata only, as sent in `available`. */
export interface WidgetMeta {
    key: string;
    title: string;
    description: string | null;
    size: WidgetSize;
    order: number;
}

export interface DashboardWidget extends WidgetMeta {
    data: Record<string, unknown>;
}

export interface DashboardPageProps {
    widgets: DashboardWidget[];
    available: WidgetMeta[];
    can: { customize: boolean };
    /** Private broadcast channel for DashboardStatsUpdated, or null outside a workspace. */
    channel: string | null;
}

/** One entry of the payload `UpdateWidgetLayoutRequest` validates. */
export interface WidgetLayoutEntry {
    key: string;
    size: WidgetSize;
    /** Inertia's request payload type requires an open record. */
    [field: string]: string;
}

/*
| Known widget payloads.
*/

export type StatDirection = 'up' | 'down' | 'flat';

export interface DashboardStat {
    key: string;
    label: string;
    value: number;
    previous: number | null;
    delta: number | null;
    direction: StatDirection;
    icon: string;
}

export interface StatsOverviewData {
    period_days: number;
    stats: DashboardStat[];
}

export interface SeriesPoint {
    bucket: string;
    label: string;
    value: number;
    /** The chart components take an open datum record. */
    [key: string]: unknown;
}

export interface RevenueChartData {
    available: boolean;
    reason: string | null;
    currency: string;
    series: SeriesPoint[];
    total: number;
}

export interface UserGrowthData {
    series: SeriesPoint[];
    total: number;
    cumulative: number;
}

export interface ActivityEntry {
    id: number | string;
    log_name: string | null;
    description: string;
    subject: string | null;
    causer: string | null;
    created_at: string | null;
}

export interface RecentActivityData {
    items: ActivityEntry[];
}

export interface LoginEntry {
    id: number | string;
    user: string | null;
    email: string | null;
    ip_address: string | null;
    browser: string | null;
    platform: string | null;
    successful: boolean;
    failure_reason: string | null;
    logged_in_at: string;
}

export interface RecentLoginsData {
    failed_recently: number;
    items: LoginEntry[];
}

export type TaskPriority = 'high' | 'medium' | 'low';

export interface TaskEntry {
    key: string;
    label: string;
    icon: string | null;
    url: string | null;
    priority: TaskPriority;
}

export interface TasksData {
    items: TaskEntry[];
    completed: number;
    total: number;
}

export interface QuickAction {
    key: string;
    label: string;
    icon: string | null;
    url: string;
}

export interface QuickActionsData {
    actions: QuickAction[];
}
