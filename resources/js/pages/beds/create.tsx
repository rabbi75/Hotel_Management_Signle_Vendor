import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { BedForm } from './bed-form';

export default function BedsCreate(props: {
    hotels: OptionMap;
    rooms: OptionMap;
    floors: OptionMap;
    statuses: EnumOption[];
    defaultHotelId?: number | null;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Beds', href: route('beds.index') },
        { label: 'New bed' },
    ];

    return (
        <AppLayout title="New bed" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="New bed"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('beds.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <BedForm {...props} />
            </div>
        </AppLayout>
    );
}
