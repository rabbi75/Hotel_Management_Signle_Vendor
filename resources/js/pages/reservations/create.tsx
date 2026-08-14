import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, OptionMap } from '@/types/reservation';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ReservationForm } from './reservation-form';

export default function ReservationsCreate(props: {
    hotels: OptionMap;
    guests: OptionMap;
    rooms: OptionMap;
    beds: OptionMap;
    roomTypes: OptionMap;
    statuses: EnumOption[];
    sources: EnumOption[];
    defaultHotelId?: number | null;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Reservations', href: route('reservations.index') },
        { label: 'New reservation' },
    ];

    return (
        <AppLayout title="New reservation" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="New reservation"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('reservations.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <ReservationForm {...props} />
            </div>
        </AppLayout>
    );
}
