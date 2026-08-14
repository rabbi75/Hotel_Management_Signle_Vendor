import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ApiTokenRow, ApiTokensPageProps, CreatedToken } from '@/types/api';
import { router, useForm } from '@inertiajs/react';
import { KeyRound, Plus, SearchX, ShieldAlert, Trash2, TriangleAlert } from 'lucide-react';
import { useEffect, useId, useState, type FormEvent } from 'react';
import { CopyButton } from '../copy-button';

interface Props extends ApiTokensPageProps {
    /** Present on exactly one render, immediately after creation. */
    created_token: CreatedToken | null;
}

interface TokenForm {
    name: string;
    abilities: string[];
    expires_in_days: number;
}

export default function ApiTokensIndex({ table, abilities, default_expiry_days, can, created_token }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const nameId = useId();
    const expiryId = useId();

    const [createOpen, setCreateOpen] = useState(false);
    const [revealed, setRevealed] = useState<CreatedToken | null>(null);

    const mayCreate = can.create && allows('api.tokens.create');
    const mayRevoke = can.revoke && allows('api.tokens.revoke');
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const form = useForm<TokenForm>({
        name: '',
        abilities: ['read'],
        expires_in_days: default_expiry_days,
    });

    // The plaintext exists on this one response only; surface it immediately so
    // it cannot be lost to a stray navigation.
    useEffect(() => {
        if (created_token) {
            setRevealed(created_token);
            setCreateOpen(false);
        }
    }, [created_token]);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.post(route('api.tokens.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function toggleAbility(ability: string, checked: boolean): void {
        form.setData('abilities', checked ? [...form.data.abilities, ability] : form.data.abilities.filter((entry) => entry !== ability));
    }

    async function revoke(row: ApiTokenRow): Promise<void> {
        const ok = await confirm({
            title: `Revoke the “${row.name}” token?`,
            description: 'Any integration still presenting it starts failing immediately. This cannot be undone.',
            variant: 'destructive',
            confirmLabel: 'Revoke token',
            confirmWord: 'revoke',
        });

        if (ok) {
            router.delete(route('api.tokens.destroy', row.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<ApiTokenRow> = {
        name: (row) => (
            <div className="min-w-0">
                <span className="block truncate text-sm font-medium">{row.name}</span>
                {row.is_expired && (
                    <Badge variant="destructive" className="mt-1">
                        Expired
                    </Badge>
                )}
            </div>
        ),
        fingerprint: (row) => <code className="font-mono text-xs text-muted-foreground">{row.fingerprint}…</code>,
        abilities: (row) => (
            <span className="flex flex-wrap gap-1">
                {row.abilities.length === 0 ? (
                    <TextCell value="—" muted />
                ) : (
                    row.abilities.map((ability) => (
                        <Badge key={ability} variant="outline">
                            {ability}
                        </Badge>
                    ))
                )}
            </span>
        ),
        last_used_at: (row) => (row.last_used_at ? <RelativeDateCell value={row.last_used_at} /> : <TextCell value="Never used" muted />),
        expires_at: (row) => (row.expires_at ? <RelativeDateCell value={row.expires_at} /> : <TextCell value="No expiry" muted />),
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
    };

    function rowActions(row: ApiTokenRow): RowAction[] {
        if (!mayRevoke) {
            return [];
        }

        return [
            {
                id: 'revoke',
                label: 'Revoke',
                destructive: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void revoke(row),
            },
        ];
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Developer' },
        { label: 'API tokens' },
    ];

    return (
        <AppLayout title="API tokens" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="API tokens"
                    description="Bearer credentials for the public REST API. Each token is bound to this workspace."
                    actions={
                        mayCreate ? (
                            <Button type="button" onClick={() => setCreateOpen(true)}>
                                <Plus className="size-4" aria-hidden="true" />
                                New token
                            </Button>
                        ) : null
                    }
                />

                <DataTable<ApiTokenRow>
                    payload={table}
                    propKey="table"
                    name="tokens"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search tokens…"
                    caption="API tokens issued for this workspace"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No tokens match these filters"
                                description="Clear the status filter to see every token."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={KeyRound}
                                title="No API tokens yet"
                                description="Issue a token to let an integration read or write this workspace's data."
                                className="border-0"
                                action={
                                    mayCreate ? (
                                        <Button type="button" size="sm" onClick={() => setCreateOpen(true)}>
                                            <Plus className="size-4" aria-hidden="true" />
                                            New token
                                        </Button>
                                    ) : null
                                }
                            />
                        )
                    }
                />
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>New API token</DialogTitle>
                        <DialogDescription>A token can never do more than its owner can. Abilities narrow it further.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} noValidate className="space-y-5">
                        <div className="grid gap-2">
                            <Label htmlFor={nameId}>Name</Label>
                            <Input
                                id={nameId}
                                value={form.data.name}
                                autoComplete="off"
                                placeholder="CI deploy"
                                required
                                aria-invalid={Boolean(form.errors.name)}
                                aria-describedby={form.errors.name ? `${nameId}-error` : undefined}
                                onChange={(event) => form.setData('name', event.target.value)}
                            />
                            {form.errors.name && (
                                <p id={`${nameId}-error`} className="text-sm font-medium text-destructive">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>

                        <fieldset className="grid gap-3">
                            <legend className="text-sm font-medium">Abilities</legend>

                            {Object.entries(abilities).map(([ability, description]) => (
                                <label key={ability} className="flex items-start gap-3 text-sm">
                                    <Checkbox
                                        checked={form.data.abilities.includes(ability)}
                                        onCheckedChange={(checked) => toggleAbility(ability, checked === true)}
                                        aria-describedby={`${nameId}-${ability}`}
                                    />
                                    <span className="min-w-0">
                                        <span className="block font-mono text-xs">{ability}</span>
                                        <span id={`${nameId}-${ability}`} className="block text-xs text-muted-foreground">
                                            {description}
                                        </span>
                                    </span>
                                </label>
                            ))}

                            {form.errors.abilities && <p className="text-sm font-medium text-destructive">{form.errors.abilities}</p>}
                        </fieldset>

                        <div className="grid gap-2">
                            <Label htmlFor={expiryId}>Expires in (days)</Label>
                            <Input
                                id={expiryId}
                                type="number"
                                min={0}
                                max={3650}
                                value={form.data.expires_in_days}
                                onChange={(event) => form.setData('expires_in_days', Number(event.target.value))}
                            />
                            <p className="text-xs text-muted-foreground">Zero issues a token that never expires.</p>
                            {form.errors.expires_in_days && (
                                <p className="text-sm font-medium text-destructive">{form.errors.expires_in_days}</p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner className="size-4" aria-hidden="true" />}
                                Create token
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={revealed !== null} onOpenChange={(open) => !open && setRevealed(null)}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Copy your token now</DialogTitle>
                        <DialogDescription>Token “{revealed?.name}” has been created.</DialogDescription>
                    </DialogHeader>

                    <Alert variant="warning">
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>You will not see this again</AlertTitle>
                        <AlertDescription>
                            Only a hash is stored, so this value cannot be recovered. If you lose it, revoke the token and issue a new one.
                        </AlertDescription>
                    </Alert>

                    <div className="grid gap-2">
                        <Label htmlFor="plaintext-token">Token</Label>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Input
                                id="plaintext-token"
                                readOnly
                                value={revealed?.plain_text ?? ''}
                                className="font-mono text-xs"
                                onFocus={(event) => event.currentTarget.select()}
                            />
                            <CopyButton
                                value={revealed?.plain_text ?? ''}
                                label="Copy token"
                                successMessage="Token copied. Store it somewhere safe."
                                className="shrink-0"
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" onClick={() => setRevealed(null)}>
                            <ShieldAlert className="size-4" aria-hidden="true" />I have stored it
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
