import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { BuildingRow, OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { BuildingForm } from './building-form';

export default function BuildingsEdit({ building, hotels }: { building: BuildingRow; hotels: OptionMap }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Buildings', href: route('buildings.index') },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${building.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={`Edit ${building.name}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('buildings.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <BuildingForm building={building} hotels={hotels} />
            </div>
        </AppLayout>
    );
}
