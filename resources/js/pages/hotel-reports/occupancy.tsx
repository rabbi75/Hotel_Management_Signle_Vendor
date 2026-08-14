import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { AreaChart } from '@/components/charts/area-chart';
import { StatCard } from '@/components/charts/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { ReportFiltersBar } from '@/pages/hotel-reports/report-filters';
import type { BreadcrumbItem } from '@/types';
import type { OccupancyReport, OptionMap, ReportFilters } from '@/types/hotel-reports';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Percent } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Props {
    report: OccupancyReport;
    hotels: OptionMap;
    filters: ReportFilters;
}

export default function OccupancyReportPage({ report, hotels, filters }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotel dashboard', href: route('hotel.dashboard') },
        { label: 'Occupancy' },
    ];

    const chartData = report.series.map((point) => ({
        date: point.date.slice(5),
        rate: point.rate,
        sold: point.sold,
    }));

    return (
        <AppLayout title="Occupancy report" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Occupancy report"
                    description="Rooms sold per night as a share of total inventory."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('hotel.dashboard')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Dashboard
                            </Link>
                        </Button>
                    }
                />

                <ReportFiltersBar filters={filters} hotels={hotels} routeName="hotel-reports.occupancy" />

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Average occupancy" value={`${report.average_occupancy}%`} icon={<Percent className="size-4" aria-hidden="true" />} />
                    <StatCard label="Total rooms" value={report.total_rooms} footer="Active inventory in scope" />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daily occupancy rate</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AreaChart
                            data={chartData}
                            xKey="date"
                            series={[{ key: 'rate', label: 'Occupancy %', color: 'var(--chart-1)' }]}
                            height={320}
                            valueFormatter={(v) => `${v}%`}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
