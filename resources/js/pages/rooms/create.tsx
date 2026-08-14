import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { RoomForm } from './room-form';

export default function RoomsCreate(props: {
    hotels: OptionMap;
    buildings: OptionMap;
    floors: OptionMap;
    roomTypes: OptionMap;
    facilities: OptionMap;
    statuses: EnumOption[];
    defaultHotelId?: number | null;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Rooms', href: route('rooms.index') },
        { label: 'New room' },
    ];

    return (
        <AppLayout title="New room" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="New room"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('rooms.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <RoomForm {...props} />
            </div>
        </AppLayout>
    );
}
