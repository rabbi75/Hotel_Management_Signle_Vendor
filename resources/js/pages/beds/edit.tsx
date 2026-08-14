import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { BedRow, EnumOption, OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { BedForm } from './bed-form';

export default function BedsEdit(props: {
    bed: BedRow;
    hotels: OptionMap;
    rooms: OptionMap;
    floors: OptionMap;
    statuses: EnumOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Beds', href: route('beds.index') },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${props.bed.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={`Edit ${props.bed.name}`}
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
