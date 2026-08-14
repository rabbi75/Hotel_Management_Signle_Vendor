import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { RoomTypeForm } from './room-type-form';

export default function RoomTypesCreate({
    hotels,
    facilities,
    defaultHotelId,
}: {
    hotels: OptionMap;
    facilities: OptionMap;
    defaultHotelId?: number | null;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Room types', href: route('room-types.index') },
        { label: 'New room type' },
    ];

    return (
        <AppLayout title="New room type" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="New room type"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('room-types.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <RoomTypeForm hotels={hotels} facilities={facilities} defaultHotelId={defaultHotelId} />
            </div>
        </AppLayout>
    );
}
