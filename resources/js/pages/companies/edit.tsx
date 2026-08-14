import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, LocaleDefinition } from '@/types';
import type { Company } from '@/types/companies';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert, UserRoundCog } from 'lucide-react';
import { useState } from 'react';
import { CompanyForm } from './company-form';

interface CompaniesEditProps {
    company: Company;
    locales: Record<string, LocaleDefinition>;
    can: { delete: boolean; transfer: boolean };
}

export default function CompaniesEdit({ company, locales, can }: CompaniesEditProps) {
    const { can: allows } = usePermissions();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Workspaces', href: route('companies.index') },
        { label: company.name, href: route('companies.show', company.uuid) },
        { label: 'Settings' },
    ];

    return (
        <AppLayout title={`${company.name} settings`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="Workspace settings"
                    description={company.name}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('companies.show', company.uuid)}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to overview
                            </Link>
                        </Button>
                    }
                />

                <CompanyForm company={company} locales={locales} />

                {(can.transfer || can.delete) && (
                    <>
                        <Separator />

                        <Card className="border-destructive/40">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-destructive">
                                    <TriangleAlert className="size-4" aria-hidden="true" />
                                    Danger zone
                                </CardTitle>
                                <CardDescription>Actions here affect everyone in this workspace.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {can.transfer && allows('companies.transfer') && (
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">Transfer ownership</p>
                                            <p className="text-sm text-muted-foreground">
                                                Hand this workspace to another member. You keep admin access unless they remove it.
                                            </p>
                                        </div>
                                        <Button asChild variant="outline">
                                            <Link href={route('companies.transfer-ownership.create')}>
                                                <UserRoundCog className="size-4" aria-hidden="true" />
                                                Transfer
                                            </Link>
                                        </Button>
                                    </div>
                                )}

                                {can.transfer && can.delete && <Separator />}

                                {can.delete && allows('companies.delete') && <DeleteWorkspace company={company} />}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

interface DeleteFormValues {
    name: string;
    [key: string]: string;
}

/**
 * Deletion is irreversible from the UI, so it asks for the workspace name
 * verbatim rather than a generic confirmation.
 */
function DeleteWorkspace({ company }: { company: Company }) {
    const [open, setOpen] = useState(false);
    const form = useForm<DeleteFormValues>({ name: '' });
    const matches = form.data.name === company.name;

    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
                <p className="text-sm font-medium">Delete this workspace</p>
                <p className="text-sm text-muted-foreground">Members lose access immediately and the data is scheduled for removal.</p>
            </div>

            <Button variant="destructive" onClick={() => setOpen(true)}>
                Delete workspace
            </Button>

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);

                    if (!next) {
                        form.reset();
                        form.clearErrors();
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Delete {company.name}?</DialogTitle>
                        <DialogDescription>This cannot be undone from the interface.</DialogDescription>
                    </DialogHeader>

                    <Alert variant="destructive">
                        <TriangleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>Everyone loses access</AlertTitle>
                        <AlertDescription>Members, departments, teams and pending invitations all go with it.</AlertDescription>
                    </Alert>

                    <div className="space-y-2">
                        <Label htmlFor="delete-workspace-name">
                            Type <span className="font-mono">{company.name}</span> to confirm
                        </Label>
                        <Input
                            id="delete-workspace-name"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            aria-invalid={form.errors.name ? true : undefined}
                            aria-describedby={form.errors.name ? 'delete-workspace-error' : undefined}
                            autoComplete="off"
                        />
                        {form.errors.name && (
                            <p id="delete-workspace-error" className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={!matches}
                            loading={form.processing}
                            onClick={() => form.delete(route('companies.destroy', company.uuid))}
                        >
                            Delete permanently
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
