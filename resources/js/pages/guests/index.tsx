import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { GuestRow } from '@/types/reservation';
import { Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, SearchX, Trash2, Users } from 'lucide-react';

interface Props {
    table: TablePayload<GuestRow>;
    can: { create: boolean };
}

export default function GuestsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Guests' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: GuestRow): Promise<void> {
        const ok = await confirm({ title: `Delete ${row.full_name}?`, variant: 'destructive', confirmLabel: 'Delete' });
        if (ok) router.delete(route('guests.destroy', row.uuid), { preserveScroll: true });
    }

    const columns: ColumnRenderers<GuestRow> = {
        full_name: (row) => (
            <Link href={route('guests.show', row.uuid)} className="font-medium underline-offset-4 hover:underline">
                {row.full_name}
            </Link>
        ),
        email: (row) => <TextCell value={row.email} muted />,
        phone: (row) => <TextCell value={row.phone} muted />,
        is_vip: (row) => (row.is_vip ? <Badge variant="outline">VIP</Badge> : <TextCell value="—" muted />),
        reservations_count: (row) => <span className="tabular-nums">{row.reservations_count ?? 0}</span>,
    };

    function rowActions(row: GuestRow): RowAction[] {
        const actions: RowAction[] = [
            { id: 'view', label: 'View', icon: <Eye className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('guests.show', row.uuid)) },
        ];
        if (allows('guests.update')) {
            actions.push({ id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('guests.edit', row.uuid)) });
        }
        if (allows('guests.delete')) {
            actions.push({ id: 'delete', label: 'Delete', destructive: true, separatorBefore: true, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => void remove(row) });
        }
        return actions;
    }

    return (
        <AppLayout title="Guests" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Guests"
                    description="Guest profiles and stay history."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('guests.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New guest
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="guests"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search guests…"
                    emptyState={<EmptyState icon={filtered ? SearchX : Users} title={filtered ? 'No matches' : 'No guests yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
