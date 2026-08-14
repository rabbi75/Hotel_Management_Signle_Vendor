import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { UserFormOptions, UserRow } from '@/types/users';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { UserForm } from './user-form';

interface UsersEditProps extends UserFormOptions {
    user: UserRow;
}

export default function UsersEdit({ user, ...options }: UsersEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Users', href: route('users.index') },
        { label: user.name, href: route('users.show', user.id) },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${user.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={`Edit ${user.name}`}
                    description={user.email}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('users.show', user.id)}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to profile
                            </Link>
                        </Button>
                    }
                />

                <UserForm user={user} {...options} />
            </div>
        </AppLayout>
    );
}
