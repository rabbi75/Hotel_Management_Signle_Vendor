import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { DepartmentNode } from '@/types/companies';
import { Link, router } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { ArrowLeft, Network, Pencil, Plus, Trash2, UserRound, Users, UsersRound } from 'lucide-react';

interface DepartmentsShowProps {
    department: DepartmentNode;
}

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PP') : '—';
}

export default function DepartmentsShow({ department }: DepartmentsShowProps) {
    const { can } = usePermissions();
    const confirm = useConfirm();
    const mayManage = can('companies.departments.manage');

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Departments', href: route('departments.index') },
        { label: department.name },
    ];

    // The subtree is loaded one level deep by the controller; deeper levels are
    // reachable by opening a child.
    const children = department.children;

    const teamsUrl = routeUrl('teams.index');
    const membersUrl = routeUrl('companies.members.index');

    async function remove(): Promise<void> {
        const ok = await confirm({
            title: `Delete the ${department.name} department?`,
            description:
                children.length > 0
                    ? 'Its child departments are re-parented, and members lose their department assignment.'
                    : 'Members lose their department assignment.',
            variant: 'destructive',
            confirmLabel: 'Delete department',
        });

        if (ok) {
            router.delete(route('departments.destroy', department.id));
        }
    }

    return (
        <AppLayout title={department.name} breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title={department.name}
                    description={
                        department.description ?? (department.parent ? `Reports into ${department.parent}.` : 'A top-level department.')
                    }
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={route('departments.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    All departments
                                </Link>
                            </Button>
                            {mayManage && (
                                <>
                                    <Button asChild>
                                        <Link href={route('departments.edit', department.id)}>
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
                            <CardDescription>Where this department sits and who runs it.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <dl className="grid gap-3 text-sm">
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Parent</dt>
                                    <dd className="min-w-0 truncate">
                                        {department.parent_id && department.parent ? (
                                            <Link
                                                href={route('departments.show', department.parent_id)}
                                                className="underline underline-offset-4"
                                            >
                                                {department.parent}
                                            </Link>
                                        ) : (
                                            'Top level'
                                        )}
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Sub-departments</dt>
                                    <dd className="tabular-nums">{children.length}</dd>
                                </div>
                                {department.teams_count !== null && (
                                    <div className="flex items-baseline justify-between gap-3">
                                        <dt className="text-muted-foreground">Teams</dt>
                                        <dd className="tabular-nums">{department.teams_count}</dd>
                                    </div>
                                )}
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Slug</dt>
                                    <dd className="truncate font-mono text-xs">{department.slug}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Created</dt>
                                    <dd>{absolute(department.created_at)}</dd>
                                </div>
                            </dl>

                            <Separator />

                            <div className="flex items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                                    <UserRound className="size-4 text-muted-foreground" aria-hidden="true" />
                                </span>
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">Manager</p>
                                    <p className="truncate text-sm font-medium">{department.manager ?? 'Not assigned'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Network className="size-4 text-muted-foreground" aria-hidden="true" />
                                Sub-departments
                            </CardTitle>
                            <CardDescription>Departments that report into {department.name}.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {children.length === 0 ? (
                                <EmptyState
                                    icon={Network}
                                    title="No sub-departments"
                                    description="This is a leaf of the structure. Add one to break it down further."
                                    action={
                                        mayManage ? (
                                            <Button asChild size="sm">
                                                <Link href={`${route('departments.create')}?parent=${department.id}`}>
                                                    <Plus className="size-4" aria-hidden="true" />
                                                    Add a sub-department
                                                </Link>
                                            </Button>
                                        ) : null
                                    }
                                />
                            ) : (
                                <ul className="divide-y divide-border">
                                    {children.map((child) => (
                                        <li
                                            key={child.id}
                                            className="flex flex-wrap items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0">
                                                <Link
                                                    href={route('departments.show', child.id)}
                                                    className="block truncate rounded-sm text-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                                >
                                                    {child.name}
                                                </Link>
                                                {child.manager && (
                                                    <p className="truncate text-xs text-muted-foreground">Managed by {child.manager}</p>
                                                )}
                                            </div>
                                            {child.teams_count !== null && child.teams_count > 0 && (
                                                <Badge variant="outline">{child.teams_count} teams</Badge>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UsersRound className="size-4 text-muted-foreground" aria-hidden="true" />
                                Teams
                            </CardTitle>
                            <CardDescription>Teams attached to this department.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {/* This screen's payload carries no team list, so the filtered index is the source of truth. */}
                            <EmptyState
                                icon={UsersRound}
                                title="Teams live on the teams screen"
                                description="Open the teams list filtered to this department to see and manage them."
                                action={
                                    teamsUrl && can('companies.teams.manage') ? (
                                        <Button asChild size="sm" variant="outline">
                                            <Link href={`${teamsUrl}?teams_filters[department_id]=${department.id}`}>
                                                View this department’s teams
                                            </Link>
                                        </Button>
                                    ) : null
                                }
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="size-4 text-muted-foreground" aria-hidden="true" />
                                Members
                            </CardTitle>
                            <CardDescription>People assigned to this department.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <EmptyState
                                icon={Users}
                                title="Members live on the members screen"
                                description="Open the members list filtered to this department to change assignments."
                                action={
                                    membersUrl && can('companies.members.view') ? (
                                        <Button asChild size="sm" variant="outline">
                                            <Link href={`${membersUrl}?members_filters[department]=${department.id}`}>
                                                View this department’s members
                                            </Link>
                                        </Button>
                                    ) : null
                                }
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
