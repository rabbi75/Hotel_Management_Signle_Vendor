import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { HotelRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';

interface Props {
    hotel: HotelRow;
    can: { update: boolean; delete: boolean; switch: boolean };
}

export default function HotelsShow({ hotel, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotels', href: route('hotels.index') },
        { label: hotel.name },
    ];

    return (
        <AppLayout title={hotel.name} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={hotel.name}
                    description={[hotel.city, hotel.country].filter(Boolean).join(', ') || 'Property details'}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={route('hotels.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back
                                </Link>
                            </Button>
                            {can.switch && (
                                <Button variant="secondary" onClick={() => router.post(route('hotels.switch', hotel.uuid))}>
                                    Work in this property
                                </Button>
                            )}
                            {can.update && (
                                <Button asChild>
                                    <Link href={route('hotels.edit', hotel.uuid)}>
                                        <Pencil className="size-4" aria-hidden="true" />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3">
                        <CardTitle>Overview</CardTitle>
                        <Badge variant="outline">{hotel.status_label}</Badge>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground">Check-in</p>
                            <p>{hotel.check_in_time}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Check-out</p>
                            <p>{hotel.check_out_time}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Currency</p>
                            <p>{hotel.currency}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Timezone</p>
                            <p>{hotel.timezone}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Phone</p>
                            <p>{hotel.phone || '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Email</p>
                            <p>{hotel.email || '—'}</p>
                        </div>
                        <div className="sm:col-span-2">
                            <p className="text-muted-foreground">Address</p>
                            <p>{hotel.address || '—'}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
