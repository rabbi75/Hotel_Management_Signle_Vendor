import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import type { BulkAction } from '@/components/data-table/data-table-bulk-actions';
import { AvatarCell, DateCell, RelativeDateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import type { ExportFormat } from '@/components/data-table/data-table-view-options';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { EnumOption, UserRow } from '@/types/users';
import { Link, router } from '@inertiajs/react';
import { CircleAlert, Pencil, Plus, RotateCcw, ShieldOff, Trash2, UserRoundSearch, Users, VenetianMask } from 'lucide-react';
import { useState } from 'react';

interface UsersIndexProps {
    table: TablePayload<UserRow>;
    statuses: EnumOption[];
    can: { create: boolean; export: boolean };
}

/** Maps a backend colour token onto the badge variants the design system ships. */
const BADGE_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    success: 'success',
    info: 'info',
    warning: 'warning',
    danger: 'destructive',
    primary: 'default',
    neutral: 'secondary',
};

/** Inertia visits are callback-based; this lets a bulk action await each one. */
function visit(method: 'post' | 'delete', url: string, data: Record<string, string> = {}): Promise<void> {
    return new Promise((resolve) => {
        router.visit(url, { method, data, preserveScroll: true, preserveState: true, onFinish: () => resolve() });
    });
}

function exportUsers(format: ExportFormat): void {
    if (format === 'print') {
        window.print();

        return;
    }

    const url = new URL(route('users.export'), window.location.origin);

    for (const [key, value] of new URLSearchParams(window.location.search)) {
        url.searchParams.append(key, value);
    }

    url.searchParams.set('format', format === 'excel' ? 'xlsx' : 'csv');
    window.location.assign(url.toString());
}

export default function UsersIndex({ table, statuses, can }: UsersIndexProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const [suspending, setSuspending] = useState<UserRow[] | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Users' }];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<UserRow> = {
        name: (row) => (
            <Link
                href={route('users.show', row.id)}
                className="block rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            >
                <AvatarCell name={row.name} subtitle={row.job_title} src={row.avatar_url} initials={row.initials} />
            </Link>
        ),
        email: (row) => <TextCell value={row.email} muted />,
        job_title: (row) => <TextCell value={row.job_title} />,
        status: (row) => <Badge variant={BADGE_VARIANT[row.status.color] ?? 'secondary'}>{row.status.label}</Badge>,
        roles: (row) =>
            row.roles && row.roles.length > 0 ? (
                <div className="flex flex-wrap gap-1">
                    {row.roles.map((role) => (
                        <Badge key={role.id} variant="outline">
                            {role.label}
                        </Badge>
                    ))}
                </div>
            ) : (
                <TextCell value="—" muted />
            ),
        last_login_at: (row) => <RelativeDateCell value={row.last_login_at} />,
        created_at: (row) => <DateCell value={row.created_at} />,
    };

    function rowActions(row: UserRow): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'view',
                label: 'View profile',
                icon: <UserRoundSearch className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('users.show', row.id)),
            },
        ];

        if (allows('users.update')) {
            actions.push({
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('users.edit', row.id)),
            });
        }

        if (allows('users.suspend')) {
            actions.push(
                row.status.value === 'suspended'
                    ? {
                          id: 'restore',
                          label: 'Restore',
                          separatorBefore: true,
                          icon: <RotateCcw className="size-4 opacity-70" aria-hidden="true" />,
                          onSelect: () => void visit('post', route('users.restore', row.id)),
                      }
                    : {
                          id: 'suspend',
                          label: 'Suspend',
                          separatorBefore: true,
                          icon: <ShieldOff className="size-4 opacity-70" aria-hidden="true" />,
                          onSelect: () => setSuspending([row]),
                      },
            );
        }

        if (allows('users.impersonate') && !row.is_super_admin) {
            actions.push({
                id: 'impersonate',
                label: 'Impersonate',
                icon: <VenetianMask className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: async () => {
                    const ok = await confirm({
                        title: `Sign in as ${row.name}?`,
                        description: 'Everything you do will be attributed to this account and recorded in the security log.',
                    });

                    if (ok) {
                        await visit('post', route('users.impersonate', row.id));
                    }
                },
            });
        }

        if (allows('users.delete')) {
            actions.push({
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: async () => {
                    const ok = await confirm({
                        title: `Delete ${row.name}?`,
                        description: 'The account is soft-deleted and can be restored by an administrator.',
                        variant: 'destructive',
                        confirmLabel: 'Delete user',
                    });

                    if (ok) {
                        await visit('delete', route('users.destroy', row.id));
                    }
                },
            });
        }

        return actions;
    }

    const bulkActions: BulkAction<UserRow>[] = [];

    if (allows('users.suspend')) {
        bulkActions.push({
            id: 'suspend',
            label: 'Suspend',
            icon: <ShieldOff className="size-4" aria-hidden="true" />,
            onSelect: (rows) => setSuspending(rows.filter((row) => row.status.value !== 'suspended')),
        });
    }

    if (allows('users.delete')) {
        bulkActions.push({
            id: 'delete',
            label: 'Delete',
            variant: 'destructive',
            icon: <Trash2 className="size-4" aria-hidden="true" />,
            onSelect: async (rows) => {
                const ok = await confirm({
                    title: `Delete ${rows.length} user${rows.length === 1 ? '' : 's'}?`,
                    description: 'Each account is soft-deleted and can be restored by an administrator.',
                    variant: 'destructive',
                    confirmWord: 'delete',
                    confirmLabel: 'Delete users',
                });

                if (!ok) {
                    return;
                }

                // There is no bulk endpoint; the rows are deleted one at a time
                // so each deletion goes through the same policy and audit trail.
                for (const row of rows) {
                    await visit('delete', route('users.destroy', row.id));
                }
            },
        });
    }

    return (
        <AppLayout title="Users" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Users"
                    description="Everyone with access to this workspace."
                    actions={
                        can.create && allows('users.create') ? (
                            <Button asChild>
                                <Link href={route('users.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New user
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                <DataTable<UserRow>
                    payload={table}
                    propKey="table"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    selectable={bulkActions.length > 0}
                    bulkActions={bulkActions}
                    rowActions={rowActions}
                    searchPlaceholder="Search by name or email…"
                    caption="Users in this workspace"
                    {...(can.export && allows('users.export') ? { onExport: exportUsers } : {})}
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={UserRoundSearch}
                                title="No users match these filters"
                                description="Try a different search term, or clear the filters to see everyone."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={Users}
                                title="No users yet"
                                description="Invite your colleagues or create the first account to get started."
                                className="border-0"
                                action={
                                    can.create && allows('users.create') ? (
                                        <Button asChild size="sm">
                                            <Link href={route('users.create')}>
                                                <Plus className="size-4" aria-hidden="true" />
                                                New user
                                            </Link>
                                        </Button>
                                    ) : null
                                }
                            />
                        )
                    }
                />
            </div>

            <SuspendDialog users={suspending} statuses={statuses} onClose={() => setSuspending(null)} />
        </AppLayout>
    );
}

interface SuspendDialogProps {
    users: UserRow[] | null;
    statuses: EnumOption[];
    onClose: () => void;
}

/**
 * Suspension always records a reason, so it cannot go through the generic
 * confirmation dialog — the reason is part of the request the server validates.
 */
function SuspendDialog({ users, statuses, onClose }: SuspendDialogProps) {
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const open = users !== null && users.length > 0;
    const tooShort = reason.trim().length < 5;
    const suspendedLabel = statuses.find((status) => status.value === 'suspended')?.label ?? 'Suspended';

    async function submit(): Promise<void> {
        if (!users || tooShort) {
            return;
        }

        setBusy(true);

        for (const user of users) {
            await visit('post', route('users.suspend', user.id), { reason: reason.trim() });
        }

        setBusy(false);
        setReason('');
        onClose();
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    setReason('');
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Suspend {users?.length === 1 ? users[0]?.name : `${users?.length ?? 0} users`}</DialogTitle>
                    <DialogDescription>
                        A suspended account cannot sign in. The reason is stored on the account and written to the security log.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-2">
                    <Label htmlFor="suspend-reason">
                        Reason
                        <span className="text-destructive" aria-hidden="true">
                            *
                        </span>
                    </Label>
                    <Textarea
                        id="suspend-reason"
                        rows={3}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        aria-invalid={reason !== '' && tooShort ? true : undefined}
                        aria-describedby="suspend-reason-hint"
                        placeholder="Repeated policy violations after a written warning."
                    />
                    <p id="suspend-reason-hint" className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        {reason !== '' && tooShort && <CircleAlert className="size-3.5 text-destructive" aria-hidden="true" />}
                        At least 5 characters. Status becomes “{suspendedLabel}”.
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={onClose} disabled={busy}>
                        Cancel
                    </Button>
                    <Button type="button" variant="destructive" onClick={() => void submit()} disabled={tooShort} loading={busy}>
                        Suspend
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
