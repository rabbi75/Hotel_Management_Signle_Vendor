import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption } from '@/types/operations';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { HousekeepingTaskForm } from './housekeeping-task-form';

interface Props {
    hotels: OptionMap;
    rooms: OptionMap;
    staff: OptionMap;
    priorities: EnumOption[];
    taskTypes: EnumOption[];
    defaultHotelId?: number | null;
}

export default function HousekeepingCreate({ hotels, rooms, staff, priorities, taskTypes, defaultHotelId }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Housekeeping', href: route('housekeeping.index') },
        { label: 'New task' },
    ];

    return (
        <AppLayout title="New housekeeping task" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="New task"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('housekeeping.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <HousekeepingTaskForm hotels={hotels} rooms={rooms} staff={staff} priorities={priorities} taskTypes={taskTypes} defaultHotelId={defaultHotelId} />
            </div>
        </AppLayout>
    );
}
