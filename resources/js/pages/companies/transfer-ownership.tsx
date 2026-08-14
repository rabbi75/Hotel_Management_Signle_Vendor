import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { TransferCandidate } from '@/types/companies';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert, UserRoundCog, Users } from 'lucide-react';
import { useState } from 'react';

interface TransferOwnershipProps {
    company: { uuid: string; name: string };
    candidates: TransferCandidate[];
}

interface TransferFormValues {
    user_id: string;
    [key: string]: string;
}

const ACKNOWLEDGEMENTS = [
    'The new owner can rename, bill for and delete this workspace.',
    'I will keep only administrator access, which the new owner can revoke.',
    'This cannot be reversed without the new owner transferring it back.',
] as const;

export default function TransferOwnership({ company, candidates }: TransferOwnershipProps) {
    const form = useForm<TransferFormValues>({ user_id: '' });
    const [acknowledged, setAcknowledged] = useState<boolean[]>(ACKNOWLEDGEMENTS.map(() => false));
    const [typedName, setTypedName] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Workspaces', href: route('companies.index') },
        { label: company.name, href: route('companies.edit', company.uuid) },
        { label: 'Transfer ownership' },
    ];

    const selected = candidates.find((candidate) => String(candidate.id) === form.data.user_id) ?? null;
    const allAcknowledged = acknowledged.every(Boolean);
    const nameMatches = typedName === company.name;
    const ready = selected !== null && allAcknowledged && nameMatches;

    return (
        <AppLayout title="Transfer ownership" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="Transfer ownership"
                    description={`Hand ${company.name} to another member.`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('companies.edit', company.uuid)}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back to settings
                            </Link>
                        </Button>
                    }
                />

                <Alert variant="warning">
                    <TriangleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This is the one action that can take the workspace away from you</AlertTitle>
                    <AlertDescription>Every step below is deliberate. Read them before you continue.</AlertDescription>
                </Alert>

                {candidates.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="There is nobody to transfer to"
                        description="Invite another member first — ownership can only move to someone already in this workspace."
                        action={
                            <Button asChild size="sm" variant="outline">
                                <Link href={route('companies.members.index')}>Go to members</Link>
                            </Button>
                        }
                    />
                ) : (
                    <form
                        noValidate
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (ready) {
                                form.post(route('companies.transfer-ownership.store'));
                            }
                        }}
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>1. Choose the new owner</CardTitle>
                                <CardDescription>Only current members are eligible.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <RadioGroup
                                    value={form.data.user_id}
                                    onValueChange={(value) => form.setData('user_id', value)}
                                    aria-label="New owner"
                                    aria-invalid={form.errors.user_id ? true : undefined}
                                    aria-describedby={form.errors.user_id ? 'transfer-owner-error' : undefined}
                                    className="space-y-2"
                                >
                                    {candidates.map((candidate) => {
                                        const id = `candidate-${candidate.id}`;

                                        return (
                                            <div key={candidate.id} className="flex items-center gap-3 rounded-md border border-border p-3">
                                                <RadioGroupItem value={String(candidate.id)} id={id} />
                                                <Label htmlFor={id} className="flex-col items-start gap-0.5 font-normal">
                                                    <span className="font-medium">{candidate.name}</span>
                                                    <span className="text-xs text-muted-foreground">{candidate.email}</span>
                                                </Label>
                                            </div>
                                        );
                                    })}
                                </RadioGroup>

                                {form.errors.user_id && (
                                    <p id="transfer-owner-error" className="mt-2 text-sm text-destructive">
                                        {form.errors.user_id}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>2. Acknowledge what changes</CardTitle>
                                <CardDescription>All three must be ticked.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <fieldset className="space-y-3">
                                    <legend className="sr-only">Consequences of transferring ownership</legend>
                                    {ACKNOWLEDGEMENTS.map((text, index) => {
                                        const id = `acknowledge-${index}`;

                                        return (
                                            <div key={text} className="flex items-start gap-3">
                                                <Checkbox
                                                    id={id}
                                                    className="mt-0.5"
                                                    checked={acknowledged[index] ?? false}
                                                    onCheckedChange={(checked) =>
                                                        setAcknowledged((current) =>
                                                            current.map((entry, position) =>
                                                                position === index ? checked === true : entry,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <Label htmlFor={id} className="font-normal">
                                                    {text}
                                                </Label>
                                            </div>
                                        );
                                    })}
                                </fieldset>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>3. Confirm the workspace</CardTitle>
                                <CardDescription>Type the name exactly as it appears.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Label htmlFor="transfer-workspace-name">
                                    Type <span className="font-mono">{company.name}</span>
                                </Label>
                                <Input
                                    id="transfer-workspace-name"
                                    value={typedName}
                                    onChange={(event) => setTypedName(event.target.value)}
                                    autoComplete="off"
                                    aria-describedby="transfer-workspace-hint"
                                />
                                <p id="transfer-workspace-hint" className="text-xs text-muted-foreground" aria-live="polite">
                                    {nameMatches ? 'The name matches.' : 'The name does not match yet.'}
                                </p>
                            </CardContent>
                        </Card>

                        <div className="flex flex-wrap items-center justify-end gap-3">
                            <span className="mr-auto text-sm text-muted-foreground" aria-live="polite">
                                {ready
                                    ? `Ready to transfer ${company.name} to ${selected?.name}.`
                                    : 'Complete all three steps to enable the transfer.'}
                            </span>
                            <Button asChild variant="ghost">
                                <Link href={route('companies.edit', company.uuid)}>Cancel</Link>
                            </Button>
                            <Button type="submit" variant="destructive" disabled={!ready} loading={form.processing}>
                                <UserRoundCog className="size-4" aria-hidden="true" />
                                Transfer ownership
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
