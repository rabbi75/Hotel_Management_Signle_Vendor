import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, HotelServiceRow } from '@/types/folio';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { HotelServiceForm } from './hotel-service-form';

interface Props {
    service: HotelServiceRow;
    hotels: OptionMap;
    categories: EnumOption[];
}

export default function HotelServicesEdit({ service, hotels, categories }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotel services', href: route('hotel-services.index') },
        { label: service.name },
    ];

    return (
        <AppLayout title={service.name} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={service.name}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('hotel-services.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <HotelServiceForm service={service} hotels={hotels} categories={categories} />
            </div>
        </AppLayout>
    );
}
