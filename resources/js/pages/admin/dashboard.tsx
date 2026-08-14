import { Icon } from '@/components/app-shell/icon';
import { PageHeader } from '@/components/app-shell/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AdminLayout } from '@/layouts/admin-layout';
import type { PlatformSummary, SignupPoint, TenantRow } from '@/types/admin';
import { Link } from '@inertiajs/react';

interface AdminDashboardProps {
    summary: PlatformSummary;
    signups: SignupPoint[];
    recent: TenantRow[];
}

function StatTile({ icon, label, value, hint }: { icon: string; label: string; value: string; hint?: string }) {
    return (
        <Card>
            <CardContent className="flex items-start justify-between gap-3 pt-6">
                <div className="min-w-0 space-y-1">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold tracking-tight">{value}</p>
                    {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
                </div>
                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <Icon name={icon} className="size-4" />
                </span>
            </CardContent>
        </Card>
    );
}

/** A minimal dependency-free bar chart — the dashboard needs a shape, not a chart library. */
function SignupsChart({ points }: { points: SignupPoint[] }) {
    const peak = Math.max(1, ...points.map((point) => point.count));

    return (
        <div className="flex h-40 items-end gap-1">
            {points.map((point) => (
                <div key={point.month} className="flex flex-1 flex-col items-center gap-1" title={`${point.month}: ${point.count}`}>
                    <div
                        className="w-full rounded-t bg-primary/70"
                        style={{ height: `${Math.round((point.count / peak) * 100)}%` }}
                        aria-hidden="true"
                    />
                    <span className="text-[10px] text-muted-foreground">{point.month.slice(5)}</span>
                </div>
            ))}
        </div>
    );
}

export default function AdminDashboard({ summary, signups, recent }: AdminDashboardProps) {
    return (
        <AdminLayout title="Platform overview" breadcrumbs={[{ label: 'Platform' }, { label: 'Overview' }]}>
            <div className="w-full space-y-6">
                <PageHeader title="Overview" description="Every workspace and subscription across the installation." />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile
                        icon="building-2"
                        label="Tenants"
                        value={String(summary.tenants.total)}
                        hint={`${summary.tenants.active} active · ${summary.tenants.new_this_month} new this month`}
                    />
                    <StatTile
                        icon="credit-card"
                        label="Active subscriptions"
                        value={String(summary.subscriptions.active)}
                        hint={`${summary.subscriptions.trialing} trialing · ${summary.subscriptions.past_due} past due`}
                    />
                    <StatTile icon="banknote" label="MRR" value={summary.mrr.formatted} hint="Normalised to monthly" />
                    <StatTile icon="users-round" label="Users" value={String(summary.users.total)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Signups</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SignupsChart points={signups} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Trials ending soon</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {summary.trials_ending.length === 0 && <p className="text-sm text-muted-foreground">Nothing in the next week.</p>}
                            {summary.trials_ending.map((trial) => (
                                <Link
                                    key={trial.uuid}
                                    href={route('admin.tenants.show', trial.uuid)}
                                    className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-accent/60"
                                >
                                    <span className="truncate">{trial.company}</span>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {new Date(trial.trial_ends_at).toLocaleDateString()}
                                    </span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent tenants</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1">
                        {recent.map((tenant) => (
                            <Link
                                key={tenant.uuid}
                                href={route('admin.tenants.show', tenant.uuid)}
                                className="flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm hover:bg-accent/60"
                            >
                                <span className="min-w-0 truncate font-medium">{tenant.name}</span>
                                <span className="flex shrink-0 items-center gap-2">
                                    {tenant.plan ? (
                                        <Badge variant="secondary">{tenant.plan}</Badge>
                                    ) : (
                                        <Badge variant="outline">No plan</Badge>
                                    )}
                                    {!tenant.is_active && <Badge variant="destructive">Suspended</Badge>}
                                </span>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
