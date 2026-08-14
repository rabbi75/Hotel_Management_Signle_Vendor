import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { HotelForm } from './hotel-form';

export default function HotelsCreate({ statuses }: { statuses: EnumOption[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Hotels', href: route('hotels.index') },
        { label: 'New hotel' },
    ];

    return (
        <AppLayout title="New hotel" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="New hotel"
                    description="Add a property this workspace will manage."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('hotels.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <HotelForm statuses={statuses} />
            </div>
        </AppLayout>
    );
}
