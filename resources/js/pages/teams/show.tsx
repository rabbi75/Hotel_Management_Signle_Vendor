import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { TeamRow } from '@/types/companies';
import { Link, router } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { ArrowLeft, Network, Pencil, Trash2, UserRound, UsersRound } from 'lucide-react';

interface TeamsShowProps {
    team: TeamRow;
}

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PP') : '—';
}

export default function TeamsShow({ team }: TeamsShowProps) {
    const { can } = usePermissions();
    const confirm = useConfirm();
    const mayManage = can('companies.teams.manage');

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Teams', href: route('teams.index') },
        { label: team.name },
    ];

    // `members` is eager-loaded on this screen, so its length is the roster size
    // even though `members_count` is only populated on the index query.
    const members = team.members;
    const memberCount = team.members_count ?? members.length;

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
        <AppLayout title={team.name} breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title={team.name}
                    description={team.description ?? (team.department ? `Part of ${team.department}.` : 'Not attached to a department.')}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={route('teams.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    All teams
                                </Link>
                            </Button>
                            {mayManage && (
                                <>
                                    <Button asChild>
                                        <Link href={route('teams.edit', team.id)}>
                                            <Pencil className="size-4" aria-hidden="true" />
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button variant="destructive" onClick={() => void remove()}>
                                        <Trash2 className="size-4" aria-hidden="true" />
                                        Delete
                                    </Button>
                                </>
                            )}
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Overview</CardTitle>
                            <CardDescription>Where this team sits and who runs it.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <span
                                    className="size-3 shrink-0 rounded-full border border-border"
                                    style={team.color ? { backgroundColor: team.color } : undefined}
                                    aria-hidden="true"
                                />
                                <p className="min-w-0 truncate font-medium">{team.name}</p>
                            </div>

                            <Separator />

                            <dl className="grid gap-3 text-sm">
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Department</dt>
                                    <dd className="min-w-0 truncate">
                                        {team.department_id && team.department ? (
                                            <Link
                                                href={route('departments.show', team.department_id)}
                                                className="underline underline-offset-4"
                                            >
                                                {team.department}
                                            </Link>
                                        ) : (
                                            'None'
                                        )}
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Members</dt>
                                    <dd className="tabular-nums">{memberCount}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Slug</dt>
                                    <dd className="truncate font-mono text-xs">{team.slug}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Created</dt>
                                    <dd>{absolute(team.created_at)}</dd>
                                </div>
                            </dl>

                            <Separator />

                            <div className="flex items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                                    <UserRound className="size-4 text-muted-foreground" aria-hidden="true" />
                                </span>
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">Team lead</p>
                                    <p className="truncate text-sm font-medium">{team.lead ?? 'Not assigned'}</p>
                                </div>
                            </div>

                            {team.department_id && (
                                <Button asChild variant="outline" size="sm" className="w-full">
                                    <Link href={route('departments.show', team.department_id)}>
                                        <Network className="size-4" aria-hidden="true" />
                                        View the department
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UsersRound className="size-4 text-muted-foreground" aria-hidden="true" />
                                Members
                            </CardTitle>
                            <CardDescription>Everyone on this team.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {members.length === 0 ? (
                                <EmptyState
                                    icon={UsersRound}
                                    title="No members yet"
                                    description="Add people to this team so the work has an owner."
                                    action={
                                        mayManage ? (
                                            <Button asChild size="sm">
                                                <Link href={route('teams.edit', team.id)}>
                                                    <Pencil className="size-4" aria-hidden="true" />
                                                    Add members
                                                </Link>
                                            </Button>
                                        ) : null
                                    }
                                />
                            ) : (
                                <ul className="grid gap-2 sm:grid-cols-2">
                                    {members.map((member) => (
                                        <li key={member.id} className="flex min-w-0 items-center gap-3 rounded-md border border-border p-3">
                                            <Avatar size="sm">
                                                <AvatarFallback className="text-xs">{member.initials}</AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                {can('users.view') ? (
                                                    <Link
                                                        href={route('users.show', member.id)}
                                                        className="block truncate rounded-sm text-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                                    >
                                                        {member.name}
                                                    </Link>
                                                ) : (
                                                    <span className="block truncate text-sm font-medium">{member.name}</span>
                                                )}
                                                {team.lead_id === member.id && (
                                                    <span className="text-xs text-muted-foreground">Team lead</span>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
