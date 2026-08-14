import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { CompanyRoleOption, InvitationRow } from '@/types/companies';
import { Link, router } from '@inertiajs/react';
import { MailPlus, MailX, SearchX, Send, Users } from 'lucide-react';
import { useState } from 'react';
import { InviteDialog } from '../invite-dialog';

interface InvitationsPageProps {
    table: TablePayload<InvitationRow>;
    roles: CompanyRoleOption[];
    permission_roles: string[];
    can: { invite: boolean };
}

const STATUS_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    warning: 'warning',
    success: 'success',
    neutral: 'secondary',
    danger: 'destructive',
    info: 'info',
};

export default function CompanyInvitations({ table, roles, permission_roles: permissionRoles, can }: InvitationsPageProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const [inviteOpen, setInviteOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Workspaces', href: route('companies.index') },
        { label: 'Members', href: route('companies.members.index') },
        { label: 'Invitations' },
    ];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<InvitationRow> = {
        email: (row) => <TextCell value={row.email} className="font-medium" />,
        role: (row) => <Badge variant="outline">{row.role_label}</Badge>,
        status: (row) => <Badge variant={STATUS_VARIANT[row.status_color] ?? 'secondary'}>{row.status_label}</Badge>,
        expires_at: (row) =>
            row.is_expired ? <span className="text-sm text-destructive">Expired</span> : <RelativeDateCell value={row.expires_at} />,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
    };

    function rowActions(row: InvitationRow): RowAction[] {
        if (!allows('companies.members.invite')) {
            return [];
        }

        return [
            {
                id: 'resend',
                label: 'Resend',
                disabled: !row.is_acceptable,
                icon: <Send className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.post(route('companies.invitations.resend', row.token), {}, { preserveScroll: true }),
            },
            {
                id: 'revoke',
                label: 'Revoke',
                destructive: true,
                separatorBefore: true,
                disabled: row.status !== 'pending',
                icon: <MailX className="size-4" aria-hidden="true" />,
                onSelect: async () => {
                    const ok = await confirm({
                        title: `Revoke the invitation to ${row.email}?`,
                        description: 'The link stops working immediately.',
                        variant: 'destructive',
                        confirmLabel: 'Revoke invitation',
                    });

                    if (ok) {
                        router.delete(route('companies.invitations.destroy', row.token), { preserveScroll: true });
                    }
                },
            },
        ];
    }

    return (
        <AppLayout title="Invitations" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Invitations"
                    description="People who have been invited but have not joined yet."
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={route('companies.members.index')}>
                                    <Users className="size-4" aria-hidden="true" />
                                    Members
                                </Link>
                            </Button>
                            {can.invite && allows('companies.members.invite') && (
                                <Button onClick={() => setInviteOpen(true)}>
                                    <MailPlus className="size-4" aria-hidden="true" />
                                    Invite member
                                </Button>
                            )}
                        </>
                    }
                />

                <DataTable<InvitationRow>
                    payload={table}
                    propKey="table"
                    name="invitations"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search by email…"
                    caption="Workspace invitations"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No invitations match these filters"
                                description="Clear the status or role filter to see them all."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={MailPlus}
                                title="No invitations"
                                description="Invite someone and their pending invitation will appear here."
                                className="border-0"
                                action={
                                    can.invite && allows('companies.members.invite') ? (
                                        <Button size="sm" onClick={() => setInviteOpen(true)}>
                                            <MailPlus className="size-4" aria-hidden="true" />
                                            Invite member
                                        </Button>
                                    ) : null
                                }
                            />
                        )
                    }
                />
            </div>

            <InviteDialog open={inviteOpen} onOpenChange={setInviteOpen} roles={roles} permissionRoles={permissionRoles} />
        </AppLayout>
    );
}
