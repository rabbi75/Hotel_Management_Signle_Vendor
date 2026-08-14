import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, OptionMap, ReservationRow } from '@/types/reservation';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ReservationForm } from './reservation-form';

export default function ReservationsEdit(props: {
    reservation: ReservationRow;
    hotels: OptionMap;
    guests: OptionMap;
    rooms: OptionMap;
    beds: OptionMap;
    roomTypes: OptionMap;
    statuses: EnumOption[];
    sources: EnumOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Reservations', href: route('reservations.index') },
        { label: props.reservation.number, href: route('reservations.show', props.reservation.id) },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${props.reservation.number}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={`Edit ${props.reservation.number}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('reservations.show', props.reservation.id)}>
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
