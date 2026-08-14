import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, OptionMap, RoomRow } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { RoomForm } from './room-form';

export default function RoomsEdit(props: {
    room: RoomRow;
    hotels: OptionMap;
    buildings: OptionMap;
    floors: OptionMap;
    roomTypes: OptionMap;
    facilities: OptionMap;
    statuses: EnumOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Rooms', href: route('rooms.index') },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit room ${props.room.number}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={`Edit room ${props.room.number}`}
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
