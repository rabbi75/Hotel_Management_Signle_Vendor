import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { AreaChart } from '@/components/charts/area-chart';
import { BarChart } from '@/components/charts/bar-chart';
import { StatCard } from '@/components/charts/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ArrivalRow, HotelDashboardSnapshot, OptionMap } from '@/types/hotel-reports';
import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BedDouble,
    Brush,
    DollarSign,
    LayoutDashboard,
    LogIn,
    LogOut,
    Percent,
    Users,
    Wrench,
} from 'lucide-react';
import { ReportFiltersBar } from '@/pages/hotel-reports/report-filters';

function formatMoney(minor: number, currency: string): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100);
}

interface Props {
    snapshot: HotelDashboardSnapshot;
    arrivals: ArrivalRow[];
    hotels: OptionMap;
    selectedHotelId: number | null;
}

export default function HotelDashboardIndex({ snapshot, arrivals, hotels, selectedHotelId }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotel dashboard' },
    ];

    return (
        <AppLayout title="Hotel dashboard" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Hotel dashboard"
                    description="Front-desk snapshot for today — occupancy, arrivals, revenue and open tasks."
                    actions={
                        <AiAssistButton
                            action="gm.daily_brief"
                            label="GM daily brief"
                            hotelId={selectedHotelId}
                            description="Narrative briefing from today's occupancy, arrivals, revenue, housekeeping and maintenance."
                        />
                    }
                />

                <ReportFiltersBar
                    filters={{ from: snapshot.date, to: snapshot.date, hotel_id: selectedHotelId ? String(selectedHotelId) : null }}
                    hotels={hotels}
                    routeName="hotel.dashboard"
                    showDates={false}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Occupancy" value={`${snapshot.occupancy_rate}%`} icon={<Percent className="size-4" aria-hidden="true" />} footer={`${snapshot.occupied_rooms} of ${snapshot.total_rooms} rooms`} />
                    <StatCard label="In-house guests" value={snapshot.in_house_guests} icon={<Users className="size-4" aria-hidden="true" />} />
                    <StatCard label="Arrivals today" value={snapshot.arrivals_today} icon={<LogIn className="size-4" aria-hidden="true" />} />
                    <StatCard label="Departures today" value={snapshot.departures_today} icon={<LogOut className="size-4" aria-hidden="true" />} />
                    <StatCard label="Revenue today" value={formatMoney(snapshot.revenue_today, snapshot.currency)} icon={<DollarSign className="size-4" aria-hidden="true" />} />
                    <StatCard label="Available rooms" value={snapshot.available_rooms} icon={<BedDouble className="size-4" aria-hidden="true" />} />
                    <StatCard label="Pending housekeeping" value={snapshot.pending_housekeeping} icon={<Brush className="size-4" aria-hidden="true" />} />
                    <StatCard label="Open maintenance" value={snapshot.open_maintenance} icon={<Wrench className="size-4" aria-hidden="true" />} />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Room status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {snapshot.room_status_breakdown.length > 0 ? (
                                <BarChart
                                    data={snapshot.room_status_breakdown.map((row) => ({ label: row.label, count: row.count }))}
                                    xKey="label"
                                    series={[{ key: 'count', label: 'Rooms', color: 'var(--chart-1)' }]}
                                    height={260}
                                    showValues
                                />
                            ) : (
                                <p className="text-sm text-muted-foreground">No active rooms in this property.</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Today&apos;s arrivals</CardTitle>
                            <Link href={route('hotel-reports.operations')} className="text-sm text-primary hover:underline">
                                Full report
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {arrivals.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No arrivals scheduled for today.</p>
                            ) : (
                                arrivals.slice(0, 8).map((row) => (
                                    <div key={row.id} className="flex items-center justify-between gap-3 text-sm">
                                        <div>
                                            <Link href={route('reservations.show', row.id)} className="font-medium hover:underline">
                                                {row.number}
                                            </Link>
                                            <p className="text-muted-foreground">
                                                {row.guest ?? 'Guest'} · Room {row.room ?? 'TBD'}
                                            </p>
                                        </div>
                                        <Badge variant="outline">{row.status_label}</Badge>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-wrap gap-3 text-sm">
                    <Link href={route('hotel-reports.occupancy')} className="inline-flex items-center gap-2 text-primary hover:underline">
                        <LayoutDashboard className="size-4" aria-hidden="true" />
                        Occupancy report
                    </Link>
                    <Link href={route('hotel-reports.revenue')} className="inline-flex items-center gap-2 text-primary hover:underline">
                        <DollarSign className="size-4" aria-hidden="true" />
                        Revenue report
                    </Link>
                    <Link href={route('hotel-reports.operations')} className="inline-flex items-center gap-2 text-primary hover:underline">
                        <ArrowLeftRight className="size-4" aria-hidden="true" />
                        Arrivals & departures
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
