import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { StatCard } from '@/components/charts/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { ReportFiltersBar } from '@/pages/hotel-reports/report-filters';
import type { BreadcrumbItem } from '@/types';
import type { ArrivalRow, OperationsReport, OptionMap, ReportFilters } from '@/types/hotel-reports';
import { Link } from '@inertiajs/react';
import { ArrowLeft, LogIn, LogOut } from 'lucide-react';

interface Props {
    report: OperationsReport;
    hotels: OptionMap;
    filters: ReportFilters;
}

function MovementList({ rows, emptyLabel }: { rows: ArrivalRow[]; emptyLabel: string }) {
    if (rows.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyLabel}</p>;
    }

    return (
        <div className="space-y-3">
            {rows.map((row) => (
                <div key={`${row.number}-${row.date}`} className="flex items-center justify-between gap-3 border-b pb-3 text-sm last:border-0 last:pb-0">
                    <div>
                        <Link href={route('reservations.show', row.id)} className="font-medium hover:underline">
                            {row.number}
                        </Link>
                        <p className="text-muted-foreground">
                            {row.guest ?? 'Guest'} · {row.date} · Room {row.room ?? 'TBD'}
                            {row.hotel ? ` · ${row.hotel}` : ''}
                        </p>
                    </div>
                    <Badge variant="outline">{row.status_label}</Badge>
                </div>
            ))}
        </div>
    );
}

export default function OperationsReportPage({ report, hotels, filters }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotel dashboard', href: route('hotel.dashboard') },
        { label: 'Arrivals & departures' },
    ];

    return (
        <AppLayout title="Arrivals & departures" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Arrivals & departures"
                    description="Expected check-ins and check-outs for the selected period."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('hotel.dashboard')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Dashboard
                            </Link>
                        </Button>
                    }
                />

                <ReportFiltersBar filters={filters} hotels={hotels} routeName="hotel-reports.operations" />

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Arrivals" value={report.summary.arrivals_count} icon={<LogIn className="size-4" aria-hidden="true" />} />
                    <StatCard label="Departures" value={report.summary.departures_count} icon={<LogOut className="size-4" aria-hidden="true" />} />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Arrivals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <MovementList rows={report.arrivals} emptyLabel="No arrivals in this period." />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Departures</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <MovementList rows={report.departures} emptyLabel="No departures in this period." />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
