import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PermissionGroups } from '@/types/roles';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { RoleForm } from './role-form';

interface RolesCreateProps {
    groups: PermissionGroups;
}

export default function RolesCreate({ groups }: RolesCreateProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Roles', href: route('roles.index') },
        { label: 'New role' },
    ];

    return (
        <AppLayout title="New role" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title="New role"
                    description="Group the permissions this role should grant."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('roles.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to roles
                            </Link>
                        </Button>
                    }
                />

                <RoleForm groups={groups} />
            </div>
        </AppLayout>
    );
}
