import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { AreaChart } from '@/components/charts/area-chart';
import { StatCard } from '@/components/charts/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { ReportFiltersBar } from '@/pages/hotel-reports/report-filters';
import type { BreadcrumbItem } from '@/types';
import type { OptionMap, ReportFilters, RevenueReport } from '@/types/hotel-reports';
import { Link } from '@inertiajs/react';
import { ArrowLeft, DollarSign } from 'lucide-react';

function formatMoney(minor: number, currency: string): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100);
}

interface Props {
    report: RevenueReport;
    hotels: OptionMap;
    filters: ReportFilters;
}

export default function RevenueReportPage({ report, hotels, filters }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotel dashboard', href: route('hotel.dashboard') },
        { label: 'Revenue' },
    ];

    const chartData = report.series.map((point) => ({
        date: point.date.slice(5),
        amount: point.amount / 100,
    }));

    return (
        <AppLayout title="Revenue report" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Revenue report"
                    description="Guest payments collected per day (folio payments, not SaaS billing)."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('hotel.dashboard')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Dashboard
                            </Link>
                        </Button>
                    }
                />

                <ReportFiltersBar filters={filters} hotels={hotels} routeName="hotel-reports.revenue" />

                <StatCard label="Total collected" value={formatMoney(report.total, report.currency)} icon={<DollarSign className="size-4" aria-hidden="true" />} />

                <Card>
                    <CardHeader>
                        <CardTitle>Daily revenue</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AreaChart
                            data={chartData}
                            xKey="date"
                            series={[{ key: 'amount', label: 'Revenue', color: 'var(--chart-2)' }]}
                            height={320}
                            valueFormatter={(v) => formatMoney(Number(v) * 100, report.currency)}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
