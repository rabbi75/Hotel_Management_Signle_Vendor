import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, WorkspaceSummary } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Briefcase, Check, Plus } from 'lucide-react';

interface OperationalWorkspacesIndexProps {
    workspaces: WorkspaceSummary[];
    current: string | null;
    can: { create: boolean };
}

export default function OperationalWorkspacesIndex({ workspaces, current, can }: OperationalWorkspacesIndexProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Operational workspaces' },
    ];

    return (
        <AppLayout title="Operational workspaces" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Operational workspaces"
                    description="Hotel operations are scoped to a workspace inside your tenant."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('operational-workspaces.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New workspace
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                {workspaces.length === 0 ? (
                    <EmptyState
                        icon={Briefcase}
                        title="No operational workspaces"
                        description="Create a workspace to organize hotels and reservations."
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {workspaces.map((workspace) => {
                            const isActive = workspace.uuid === current;

                            return (
                                <Card key={workspace.uuid} className={isActive ? 'border-primary' : undefined}>
                                    <CardContent className="flex items-start gap-3 py-4">
                                        <Avatar className="size-10 rounded-md">
                                            {workspace.logo && <AvatarImage src={workspace.logo} alt="" />}
                                            <AvatarFallback className="rounded-md">{workspace.initials}</AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0 flex-1 space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="truncate font-medium">{workspace.name}</p>
                                                {workspace.is_default && <Badge variant="secondary">Default</Badge>}
                                                {isActive && <Badge>Active</Badge>}
                                            </div>
                                            <Button
                                                size="sm"
                                                variant={isActive ? 'secondary' : 'default'}
                                                disabled={isActive}
                                                onClick={() =>
                                                    router.post(route('operational-workspaces.switch', workspace.uuid), {}, { preserveScroll: true })
                                                }
                                            >
                                                {isActive ? (
                                                    <>
                                                        <Check className="size-4" aria-hidden="true" />
                                                        Current
                                                    </>
                                                ) : (
                                                    'Switch'
                                                )}
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
