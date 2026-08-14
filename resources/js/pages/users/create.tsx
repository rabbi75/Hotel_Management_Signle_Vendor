import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { UserFormOptions } from '@/types/users';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { UserForm } from './user-form';

type UsersCreateProps = UserFormOptions;

export default function UsersCreate(props: UsersCreateProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Users', href: route('users.index') },
        { label: 'New user' },
    ];

    return (
        <AppLayout title="New user" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="New user"
                    description="Create an account and place it in this workspace."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('users.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to users
                            </Link>
                        </Button>
                    }
                />

                <UserForm {...props} />
            </div>
        </AppLayout>
    );
}
