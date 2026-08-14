import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { DepartmentNode, OptionMap } from '@/types/companies';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { DepartmentForm } from './department-form';

interface DepartmentsEditProps {
    department: DepartmentNode;
    departments: OptionMap;
}

export default function DepartmentsEdit({ department, departments }: DepartmentsEditProps) {
    const { can } = usePermissions();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Departments', href: route('departments.index') },
        { label: department.name, href: route('departments.show', department.id) },
        { label: 'Edit' },
    ];

    async function remove(): Promise<void> {
        const ok = await confirm({
            title: `Delete the ${department.name} department?`,
            description: 'Members lose their department assignment, and any child departments are re-parented.',
            variant: 'destructive',
            confirmLabel: 'Delete department',
        });

        if (ok) {
            router.delete(route('departments.destroy', department.id));
        }
    }

    return (
        <AppLayout title={`Edit ${department.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={`Edit ${department.name}`}
                    description={department.parent ? `Reports into ${department.parent}.` : 'A top-level department.'}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={route('departments.show', department.id)}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back to overview
                                </Link>
                            </Button>
                            {can('companies.departments.manage') && (
                                <Button variant="destructive" onClick={() => void remove()}>
                                    <Trash2 className="size-4" aria-hidden="true" />
                                    Delete
                                </Button>
                            )}
                        </>
                    }
                />

                <DepartmentForm department={department} departments={departments} />
            </div>
        </AppLayout>
    );
}
