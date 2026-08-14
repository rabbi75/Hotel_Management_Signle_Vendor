import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { OptionMap } from '@/types/companies';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { TeamForm } from './team-form';

interface TeamsCreateProps {
    departments: OptionMap;
    members: OptionMap;
}

export default function TeamsCreate({ departments, members }: TeamsCreateProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Teams', href: route('teams.index') },
        { label: 'New team' },
    ];

    return (
        <AppLayout title="New team" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="New team"
                    description="Name the team, pick a lead, and add the people who belong to it."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('teams.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to teams
                            </Link>
                        </Button>
                    }
                />

                <TeamForm departments={departments} members={members} />
            </div>
        </AppLayout>
    );
}
