import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, HotelRow } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { HotelForm } from './hotel-form';

export default function HotelsEdit({ hotel, statuses }: { hotel: HotelRow; statuses: EnumOption[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotels', href: route('hotels.index') },
        { label: hotel.name, href: route('hotels.show', hotel.uuid) },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${hotel.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={`Edit ${hotel.name}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('hotels.show', hotel.uuid)}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <HotelForm hotel={hotel} statuses={statuses} />
            </div>
        </AppLayout>
    );
}
