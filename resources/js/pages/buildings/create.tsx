import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { BuildingForm } from './building-form';

export default function BuildingsCreate({ hotels, defaultHotelId }: { hotels: OptionMap; defaultHotelId?: number | null }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Buildings', href: route('buildings.index') },
        { label: 'New building' },
    ];

    return (
        <AppLayout title="New building" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="New building"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('buildings.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <BuildingForm hotels={hotels} defaultHotelId={defaultHotelId} />
            </div>
        </AppLayout>
    );
}
