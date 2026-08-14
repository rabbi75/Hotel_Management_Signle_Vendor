import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, GuestRow, OptionMap } from '@/types/reservation';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { GuestForm } from './guest-form';

export default function GuestsEdit({ guest, hotels, genders }: { guest: GuestRow; hotels: OptionMap; genders: EnumOption[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Guests', href: route('guests.index') },
        { label: guest.full_name, href: route('guests.show', guest.uuid) },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${guest.full_name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={`Edit ${guest.full_name}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('guests.show', guest.uuid)}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <GuestForm guest={guest} hotels={hotels} genders={genders} />
            </div>
        </AppLayout>
    );
}
