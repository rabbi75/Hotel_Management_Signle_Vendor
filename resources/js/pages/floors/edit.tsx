import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { FloorRow, OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FloorForm } from './floor-form';

export default function FloorsEdit({ floor, hotels, buildings }: { floor: FloorRow; hotels: OptionMap; buildings: OptionMap }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Floors', href: route('floors.index') },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${floor.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={`Edit ${floor.name}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('floors.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <FloorForm floor={floor} hotels={hotels} buildings={buildings} />
            </div>
        </AppLayout>
    );
}
