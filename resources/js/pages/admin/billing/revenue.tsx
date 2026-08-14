import { PageHeader } from '@/components/app-shell/page-header';
import { AreaChart } from '@/components/charts/area-chart';
import { BarChart } from '@/components/charts/bar-chart';
import { LineChart } from '@/components/charts/line-chart';
import { StatCard } from '@/components/charts/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AdminLayout } from '@/layouts/admin-layout';
import type { BreadcrumbItem } from '@/types';
import type { RevenueOverview } from '@/types/billing';
import { CircleAlert, CreditCard, TrendingUp, Users, Wallet } from 'lucide-react';
import { useMemo } from 'react';

/*
|------------------------------------------------------------------------------
| Revenue
|------------------------------------------------------------------------------
|
| Form is chosen per question, not per available chart type:
|
|   MRR, ARR, ARPA, collected, exposure  -> single headline numbers, so stat
|                                           tiles rather than plots.
|   Collected revenue over time          -> change over time, one series: area.
|   Churn over time                      -> also change over time, but a rate,
|                                           not money. A separate chart, never a
|                                           second y-axis on the revenue one.
|   MRR by plan                          -> comparing magnitudes across a few
|                                           named categories: horizontal bars,
|                                           which are read more accurately than
|                                           the slices of a donut.
|
| Every chart carries exactly one series, so colour never encodes identity here
| and no categorical palette is assigned; each uses the system's first chart
| token. Amounts arrive pre-formatted from Money, so the client never does
| currency arithmetic.
|
*/

interface RevenuePageProps {
    overview: RevenueOverview;
    months: number;
}

/** "2026-07" -> "Jul" — the year is implied by a rolling window. */
function monthLabel(value: string): string {
    const [year, month] = value.split('-');

    if (!year || !month) {
        return value;
    }

    return new Date(Number(year), Number(month) - 1, 1).toLocaleDateString(undefined, { month: 'short' });
}

export default function AdminRevenuePage({ overview, months }: RevenuePageProps) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Platform' }, { label: 'Revenue' }];

    const trend = useMemo(
        () =>
            overview.trend.map((row) => ({
                month: row.month,
                collected: row.collected.amount / 100,
                formatted: row.collected.formatted,
            })),
        [overview.trend],
    );

    const churn = useMemo(
        () => overview.churn.map((row) => ({ month: row.month, rate: row.rate, ended: row.ended, base: row.base })),
        [overview.churn],
    );

    const planMix = useMemo(
        () =>
            overview.plan_mix.map((row) => ({
                plan: row.plan,
                mrr: row.mrr.amount / 100,
                formatted: row.mrr.formatted,
                accounts: row.accounts,
            })),
        [overview.plan_mix],
    );

    // Feeds the MRR tile's sparkline: the shape of collection over the window.
    const sparkline = useMemo(() => trend.map((row) => row.collected), [trend]);

    const currency = overview.mrr.currency;
    const money = (value: number | string): string =>
        typeof value === 'number' ? `${currency} ${value.toLocaleString(undefined, { maximumFractionDigits: 0 })}` : String(value);

    return (
        <AdminLayout title="Revenue" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Revenue"
                    description={`Recurring revenue, collection and churn across every workspace, over the last ${months} months.`}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="MRR"
                        value={overview.mrr.formatted}
                        icon={<TrendingUp className="size-4" aria-hidden="true" />}
                        trend={sparkline}
                        footer={`${overview.arr.formatted} annualised`}
                    />
                    <StatCard
                        label="Paying accounts"
                        value={overview.paying_accounts.toLocaleString()}
                        icon={<Users className="size-4" aria-hidden="true" />}
                        footer={`${overview.trialing_accounts} on trial · ${overview.arpa.formatted} average`}
                    />
                    <StatCard
                        label="Collected this month"
                        value={overview.collected_this_month.formatted}
                        icon={<Wallet className="size-4" aria-hidden="true" />}
                        footer="Succeeded charges, less refunds"
                    />
                    <StatCard
                        label="Overdue"
                        value={overview.outstanding.formatted}
                        icon={<CircleAlert className="size-4" aria-hidden="true" />}
                        footer={`${overview.failed_payments} failed payment${overview.failed_payments === 1 ? '' : 's'}`}
                    />
                </div>

                <AreaChart
                    title="Collected revenue"
                    description="Succeeded charges less refunds, by the month they were processed."
                    summary={`Collected revenue for the last ${months} months, ending at ${trend.at(-1)?.formatted ?? 'nothing'}.`}
                    data={trend}
                    xKey="month"
                    xTickFormatter={monthLabel}
                    series={[{ key: 'collected', label: 'Collected' }]}
                    valueFormatter={money}
                    height={280}
                    empty="No payments have been recorded yet."
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    <BarChart
                        title="MRR by plan"
                        description="Where the recurring revenue comes from."
                        summary={planMix.map((row) => `${row.plan}: ${row.formatted} from ${row.accounts} accounts`).join('; ')}
                        data={planMix}
                        xKey="plan"
                        horizontal
                        showValues
                        series={[{ key: 'mrr', label: 'MRR' }]}
                        valueFormatter={money}
                        height={280}
                        empty="No paying subscriptions yet."
                    />

                    <LineChart
                        title="Churn"
                        description="Subscriptions that ended, as a share of those live at the start of the month."
                        summary={churn.map((row) => `${row.month}: ${row.rate}%`).join('; ')}
                        data={churn}
                        xKey="month"
                        xTickFormatter={monthLabel}
                        showDots
                        series={[{ key: 'rate', label: 'Churn rate', color: 'var(--chart-4)' }]}
                        valueFormatter={(value) => `${value}%`}
                        height={280}
                        empty="Not enough history to measure churn."
                    />
                </div>

                {/*
                    The table view the charts stand in for: identity and exact
                    values without reading a plot, which is also the accessible
                    path to the same numbers.
                */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Plan breakdown</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <caption className="sr-only">Monthly recurring revenue and account count per plan</caption>
                                <thead>
                                    <tr className="border-b border-border text-left text-muted-foreground">
                                        <th scope="col" className="px-5 py-2.5 font-medium">
                                            Plan
                                        </th>
                                        <th scope="col" className="px-5 py-2.5 text-right font-medium">
                                            Accounts
                                        </th>
                                        <th scope="col" className="px-5 py-2.5 text-right font-medium">
                                            MRR
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {planMix.length === 0 ? (
                                        <tr>
                                            <td colSpan={3} className="px-5 py-6 text-center text-muted-foreground">
                                                No paying subscriptions yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        planMix.map((row) => (
                                            <tr key={row.plan} className="border-b border-border last:border-0">
                                                <td className="px-5 py-2.5 font-medium text-foreground">{row.plan}</td>
                                                <td className="px-5 py-2.5 text-right tabular-nums">{row.accounts}</td>
                                                <td className="px-5 py-2.5 text-right tabular-nums">{row.formatted}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t border-border font-medium">
                                        <td className="px-5 py-2.5">Total</td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">{overview.paying_accounts}</td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">{overview.mrr.formatted}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <CreditCard className="size-3.5" aria-hidden="true" />A yearly subscription contributes a twelfth of its price to MRR;
                    trials contribute nothing until they convert.
                </p>
            </div>
        </AdminLayout>
    );
}
