import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { OptionMap } from '@/types/companies';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { DepartmentForm } from './department-form';

interface DepartmentsCreateProps {
    departments: OptionMap;
}

/**
 * The tree's "add a child" affordance links here with `?parent=<id>`. The
 * controller has no such prop, so the hint is read from the URL and only
 * honoured when it names a department the user was already shown.
 */
function preselectedParent(departments: OptionMap): string | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    const parent = new URLSearchParams(window.location.search).get('parent');

    return parent && parent in departments ? parent : undefined;
}

export default function DepartmentsCreate({ departments }: DepartmentsCreateProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Departments', href: route('departments.index') },
        { label: 'New department' },
    ];

    return (
        <AppLayout title="New department" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="New department"
                    description="Give it a name and, if it reports into another department, a parent."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('departments.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to departments
                            </Link>
                        </Button>
                    }
                />

                <DepartmentForm departments={departments} defaultParentId={preselectedParent(departments)} />
            </div>
        </AppLayout>
    );
}
