import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { OptionMap, TeamRow } from '@/types/companies';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { TeamForm } from './team-form';

interface TeamsEditProps {
    team: TeamRow;
    departments: OptionMap;
    members: OptionMap;
}

export default function TeamsEdit({ team, departments, members }: TeamsEditProps) {
    const { can } = usePermissions();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Teams', href: route('teams.index') },
        { label: team.name, href: route('teams.show', team.id) },
        { label: 'Edit' },
    ];

    async function remove(): Promise<void> {
        const ok = await confirm({
            title: `Delete the ${team.name} team?`,
            description: 'Members stay in the workspace but lose this team assignment.',
            variant: 'destructive',
            confirmLabel: 'Delete team',
        });

        if (ok) {
            router.delete(route('teams.destroy', team.id));
        }
    }

    return (
        <AppLayout title={`Edit ${team.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={`Edit ${team.name}`}
                    description={team.department ? `Part of ${team.department}.` : 'Not attached to a department.'}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={route('teams.show', team.id)}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back to overview
                                </Link>
                            </Button>
                            {can('companies.teams.manage') && (
                                <Button variant="destructive" onClick={() => void remove()}>
                                    <Trash2 className="size-4" aria-hidden="true" />
                                    Delete
                                </Button>
                            )}
                        </>
                    }
                />

                <TeamForm team={team} departments={departments} members={members} />
            </div>
        </AppLayout>
    );
}
