import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption } from '@/types/operations';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { MaintenanceRequestForm } from './maintenance-request-form';

interface Props {
    hotels: OptionMap;
    rooms: OptionMap;
    beds: OptionMap;
    staff: OptionMap;
    categories: EnumOption[];
    priorities: EnumOption[];
    defaultHotelId?: number | null;
}

export default function MaintenanceCreate({ hotels, rooms, beds, staff, categories, priorities, defaultHotelId }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Maintenance', href: route('maintenance.index') },
        { label: 'Report issue' },
    ];

    return (
        <AppLayout title="Report maintenance issue" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="Report issue"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('maintenance.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <MaintenanceRequestForm hotels={hotels} rooms={rooms} beds={beds} staff={staff} categories={categories} priorities={priorities} defaultHotelId={defaultHotelId} />
            </div>
        </AppLayout>
    );
}
