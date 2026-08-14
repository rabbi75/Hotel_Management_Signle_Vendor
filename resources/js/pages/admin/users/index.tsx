import { PageHeader } from '@/components/app-shell/page-header';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { AvatarCell, DateCell, RelativeDateCell } from '@/components/data-table/data-table-cells';
import type { RowAction } from '@/components/data-table/data-table-cells';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { AdminLayout } from '@/layouts/admin-layout';
import type { EnumOption } from '@/types/users';
import type { PlatformUserRow } from '@/types/admin';
import type { TablePayload } from '@/types';
import { router } from '@inertiajs/react';
import { RotateCcw, ShieldOff, UsersRound } from 'lucide-react';

interface AdminUsersProps {
    table: TablePayload<PlatformUserRow>;
    statuses: EnumOption[];
}

const columns: ColumnRenderers<PlatformUserRow> = {
    name: (row) => <AvatarCell name={row.name} subtitle={row.email} src={row.avatar} initials={row.initials} />,
    workspaces: (row) => (
        <span className="flex flex-wrap gap-1">
            {row.workspaces.length === 0 && <span className="text-muted-foreground">—</span>}
            {row.workspaces.slice(0, 3).map((workspace) => (
                <Badge key={workspace.uuid} variant="outline">
                    {workspace.name}
                </Badge>
            ))}
            {row.workspaces.length > 3 && <Badge variant="secondary">+{row.workspaces.length - 3}</Badge>}
        </span>
    ),
    status: (row) =>
        row.status === 'active' ? (
            <Badge variant="success">Active</Badge>
        ) : (
            <Badge variant="warning">{row.status}</Badge>
        ),
    last_login_at: (row) => <RelativeDateCell value={row.last_login_at} />,
    created_at: (row) => <DateCell value={row.created_at} />,
};

function rowActions(row: PlatformUserRow): RowAction[] {
    if (row.is_super_admin) {
        return [];
    }

    if (row.status === 'suspended') {
        return [
            {
                id: 'restore',
                label: 'Restore',
                icon: <RotateCcw className="size-4" />,
                onSelect: () => router.patch(route('admin.users.restore', row.id), {}, { preserveScroll: true }),
            },
        ];
    }

    return [
        {
            id: 'suspend',
            label: 'Suspend',
            icon: <ShieldOff className="size-4" />,
            destructive: true,
            onSelect: () => router.patch(route('admin.users.suspend', row.id), {}, { preserveScroll: true }),
        },
    ];
}

export default function AdminUsers({ table }: AdminUsersProps) {
    return (
        <AdminLayout title="Users" breadcrumbs={[{ label: 'Platform' }, { label: 'Users' }]}>
            <div className="space-y-6">
                <PageHeader title="Users" description="Every user across every workspace." />

                <DataTable
                    payload={table}
                    propKey="table"
                    columns={columns}
                    rowActions={rowActions}
                    searchPlaceholder="Search users…"
                    emptyState={<EmptyState icon={UsersRound} title="No users" description="No users match these filters." />}
                />
            </div>
        </AdminLayout>
    );
}
