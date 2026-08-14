import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, LocaleDefinition } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { CompanyForm } from './company-form';

interface CompaniesCreateProps {
    defaults: { timezone: string; currency: string; locale: string };
    locales: Record<string, LocaleDefinition>;
}

export default function CompaniesCreate({ defaults, locales }: CompaniesCreateProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Workspaces', href: route('companies.index') },
        { label: 'New workspace' },
    ];

    return (
        <AppLayout title="New workspace" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="New workspace"
                    description="You become its owner, and it becomes your active workspace straight away."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('companies.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to workspaces
                            </Link>
                        </Button>
                    }
                />

                <CompanyForm defaults={defaults} locales={locales} />
            </div>
        </AppLayout>
    );
}
