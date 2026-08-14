import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, MaintenanceRequestRow } from '@/types/operations';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { MaintenanceRequestForm } from './maintenance-request-form';

interface Props {
    workOrder: MaintenanceRequestRow;
    hotels: OptionMap;
    rooms: OptionMap;
    beds: OptionMap;
    staff: OptionMap;
    categories: EnumOption[];
    priorities: EnumOption[];
}

export default function MaintenanceEdit({ workOrder, hotels, rooms, beds, staff, categories, priorities }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Maintenance', href: route('maintenance.index') },
        { label: workOrder.number, href: route('maintenance.show', workOrder.id) },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${workOrder.number}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="Edit work order"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('maintenance.show', workOrder.id)}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <MaintenanceRequestForm workOrder={workOrder} hotels={hotels} rooms={rooms} beds={beds} staff={staff} categories={categories} priorities={priorities} />
            </div>
        </AppLayout>
    );
}
