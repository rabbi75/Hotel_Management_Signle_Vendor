import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Link, useForm } from '@inertiajs/react';

interface OperationalWorkspaceCreateProps {
    defaults: {
        timezone: string;
        currency: string;
    };
}

export default function OperationalWorkspaceCreate({ defaults }: OperationalWorkspaceCreateProps) {
    const form = useForm({
        name: '',
        code: '',
        description: '',
        timezone: defaults.timezone,
        currency: defaults.currency,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Operational workspaces', href: routeUrl('operational-workspaces.index') ?? undefined },
        { label: 'Create' },
    ];

    return (
        <AppLayout title="Create operational workspace" breadcrumbs={breadcrumbs}>
            <form
                className="mx-auto max-w-xl space-y-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('operational-workspaces.store'));
                }}
            >
                <PageHeader title="Create operational workspace" description="Add another operational boundary for hotels and reservations." />

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                            {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="code">Code</Label>
                            <Input id="code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                            />
                        </div>
                    </CardContent>
                    <CardFooter className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('operational-workspaces.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Create workspace
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </AppLayout>
    );
}
