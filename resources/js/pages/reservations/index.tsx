import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { ReservationRow } from '@/types/reservation';
import { Link, router } from '@inertiajs/react';
import { CalendarDays, Eye, Pencil, Plus, SearchX } from 'lucide-react';

interface Props {
    table: TablePayload<ReservationRow>;
    can: { create: boolean; calendar: boolean };
}

export default function ReservationsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Reservations' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<ReservationRow> = {
        number: (row) => (
            <Link href={route('reservations.show', row.id)} className="font-medium underline-offset-4 hover:underline">
                {row.number}
            </Link>
        ),
        guest: (row) => <TextCell value={row.guest} />,
        room: (row) => <TextCell value={row.room ?? row.bed} muted />,
        check_in_date: (row) => <span className="tabular-nums">{row.check_in_date}</span>,
        check_out_date: (row) => <span className="tabular-nums">{row.check_out_date}</span>,
        status: (row) => <Badge variant="outline">{row.status_label}</Badge>,
        total: (row) => <span className="tabular-nums">{row.total}</span>,
    };

    function rowActions(row: ReservationRow): RowAction[] {
        const actions: RowAction[] = [
            { id: 'view', label: 'View', icon: <Eye className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('reservations.show', row.id)) },
        ];
        if (allows('reservations.update') && row.can_cancel) {
            actions.push({ id: 'edit', label: 'Edit', icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />, onSelect: () => router.visit(route('reservations.edit', row.id)) });
        }
        return actions;
    }

    return (
        <AppLayout title="Reservations" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Reservations"
                    description="Bookings, check-ins and check-outs."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can.calendar && (
                                <Button asChild variant="outline">
                                    <Link href={route('reservations.calendar')}>
                                        <CalendarDays className="size-4" aria-hidden="true" />
                                        Calendar
                                    </Link>
                                </Button>
                            )}
                            {can.create && (
                                <Button asChild>
                                    <Link href={route('reservations.create')}>
                                        <Plus className="size-4" aria-hidden="true" />
                                        New reservation
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="reservations"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search reservations…"
                    emptyState={<EmptyState icon={filtered ? SearchX : CalendarDays} title={filtered ? 'No matches' : 'No reservations yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
