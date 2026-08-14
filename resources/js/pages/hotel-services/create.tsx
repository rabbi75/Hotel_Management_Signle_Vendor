import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption } from '@/types/folio';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { HotelServiceForm } from './hotel-service-form';

export default function HotelServicesCreate({
    hotels,
    categories,
    defaultHotelId,
}: {
    hotels: OptionMap;
    categories: EnumOption[];
    defaultHotelId?: number | null;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotel services', href: route('hotel-services.index') },
        { label: 'New service' },
    ];

    return (
        <AppLayout title="New service" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="New service"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('hotel-services.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <HotelServiceForm hotels={hotels} categories={categories} defaultHotelId={defaultHotelId} />
            </div>
        </AppLayout>
    );
}
