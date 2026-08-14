import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PermissionGroups, RoleSummary } from '@/types/roles';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { RoleForm } from './role-form';

interface RolesEditProps {
    role: RoleSummary;
    groups: PermissionGroups;
}

export default function RolesEdit({ role, groups }: RolesEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Roles', href: route('roles.index') },
        { label: role.label },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${role.label}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={`Edit ${role.label}`}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {role.description ?? 'No description declared for this role.'}
                            <Badge variant="outline">{role.users_count ?? 0} users</Badge>
                        </span>
                    }
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('roles.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to roles
                            </Link>
                        </Button>
                    }
                />

                <RoleForm role={role} groups={groups} />
            </div>
        </AppLayout>
    );
}
