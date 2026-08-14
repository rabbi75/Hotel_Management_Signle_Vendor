import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { AvatarCell, RelativeDateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { CompanyRoleOption, MemberRow, OptionMap } from '@/types/companies';
import { Link, router } from '@inertiajs/react';
import { Crown, MailPlus, SearchX, UserMinus, Users } from 'lucide-react';
import { useState } from 'react';
import { InviteDialog } from '../invite-dialog';

interface MembersPageProps {
    table: TablePayload<MemberRow>;
    roles: CompanyRoleOption[];
    departments: OptionMap;
    permission_roles: string[];
    owner_id: number;
    can: { invite: boolean; update: boolean; remove: boolean };
}

const ROLE_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    primary: 'default',
    info: 'info',
    warning: 'warning',
    danger: 'destructive',
    neutral: 'secondary',
};

export default function CompanyMembers({
    table,
    roles,
    departments,
    permission_roles: permissionRoles,
    owner_id: ownerId,
    can,
}: MembersPageProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const [inviteOpen, setInviteOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Workspaces', href: route('companies.index') },
        { label: 'Members' },
    ];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;
    const mayEditRoles = can.update && allows('companies.members.update');

    // The owner's standing is only changed through an explicit ownership
    // transfer, so their row is read-only here.
    const isOwner = (row: MemberRow): boolean => row.id === ownerId;

    const columns: ColumnRenderers<MemberRow> = {
        name: (row) => (
            <div className="flex min-w-0 items-center gap-2">
                <AvatarCell name={row.name} subtitle={row.job_title} initials={row.initials} />
                {isOwner(row) && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>
                                <Crown className="size-3.5 text-warning" aria-label="Workspace owner" />
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>The owner cannot be demoted or removed here.</TooltipContent>
                    </Tooltip>
                )}
            </div>
        ),
        email: (row) => <TextCell value={row.email} muted />,
        role: (row) => {
            if (!mayEditRoles || isOwner(row)) {
                return (
                    <Badge variant={ROLE_VARIANT[row.role_color ?? 'neutral'] ?? 'secondary'}>{row.role_label ?? row.role ?? '—'}</Badge>
                );
            }

            return (
                <Select
                    value={row.role ?? ''}
                    onValueChange={(value) =>
                        router.patch(
                            route('companies.members.update', row.uuid),
                            { role: value, department_id: row.department_id, job_title: row.job_title },
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                >
                    <SelectTrigger className="h-8 w-36" aria-label={`Workspace role for ${row.name}`}>
                        <SelectValue placeholder="Set role" />
                    </SelectTrigger>
                    <SelectContent>
                        {roles
                            .filter((role) => role.value !== 'owner')
                            .map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
            );
        },
        department: (row) => <TextCell value={row.department ?? departments[String(row.department_id ?? '')] ?? null} />,
        joined_at: (row) => <RelativeDateCell value={row.joined_at} />,
    };

    function rowActions(row: MemberRow): RowAction[] {
        const actions: RowAction[] = [];

        if (allows('users.view')) {
            actions.push({
                id: 'profile',
                label: 'View profile',
                onSelect: () => router.visit(route('users.show', row.id)),
            });
        }

        if (can.remove && allows('companies.members.remove')) {
            actions.push({
                id: 'remove',
                label: 'Remove from workspace',
                destructive: true,
                separatorBefore: actions.length > 0,
                disabled: isOwner(row),
                icon: <UserMinus className="size-4" aria-hidden="true" />,
                onSelect: async () => {
                    const ok = await confirm({
                        title: `Remove ${row.name}?`,
                        description: 'They lose access to this workspace immediately. Their account itself is not deleted.',
                        variant: 'destructive',
                        confirmLabel: 'Remove member',
                    });

                    if (ok) {
                        router.delete(route('companies.members.destroy', row.uuid), { preserveScroll: true });
                    }
                },
            });
        }

        return actions;
    }

    return (
        <AppLayout title="Members" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Members"
                    description="Everyone in this workspace, and what they may do here."
                    actions={
                        <>
                            {allows('companies.members.invite') && (
                                <Button asChild variant="outline">
                                    <Link href={route('companies.invitations.index')}>Pending invitations</Link>
                                </Button>
                            )}
                            {can.invite && allows('companies.members.invite') && (
                                <Button onClick={() => setInviteOpen(true)}>
                                    <MailPlus className="size-4" aria-hidden="true" />
                                    Invite member
                                </Button>
                            )}
                        </>
                    }
                />

                <DataTable<MemberRow>
                    payload={table}
                    propKey="table"
                    name="members"
                    columns={columns}
                    getRowId={(row) => row.uuid}
                    rowActions={rowActions}
                    searchPlaceholder="Search members…"
                    caption="Members of this workspace"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No members match these filters"
                                description="Try a different search term, or clear the role and department filters."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={Users}
                                title="No members yet"
                                description="Invite your colleagues to collaborate in this workspace."
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
