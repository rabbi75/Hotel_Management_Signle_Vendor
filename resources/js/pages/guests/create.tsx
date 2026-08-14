import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, OptionMap } from '@/types/reservation';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { GuestForm } from './guest-form';

export default function GuestsCreate({
    hotels,
    genders,
    defaultHotelId,
}: {
    hotels: OptionMap;
    genders: EnumOption[];
    defaultHotelId?: number | null;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Guests', href: route('guests.index') },
        { label: 'New guest' },
    ];

    return (
        <AppLayout title="New guest" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="New guest"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('guests.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <GuestForm hotels={hotels} genders={genders} defaultHotelId={defaultHotelId} />
            </div>
        </AppLayout>
    );
}
