import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { TextCell } from '@/components/data-table/data-table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { HousekeepingTaskRow } from '@/types/operations';
import { Link, router } from '@inertiajs/react';
import { Brush, Plus, SearchX } from 'lucide-react';

interface Props {
    table: TablePayload<HousekeepingTaskRow>;
    can: { create: boolean };
}

export default function HousekeepingIndex({ table, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Housekeeping' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<HousekeepingTaskRow> = {
        number: (row) => (
            <button type="button" className="font-medium text-primary hover:underline" onClick={() => router.visit(route('housekeeping.show', row.id))}>
                {row.number}
            </button>
        ),
        room: (row) => <TextCell value={row.room} />,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        status: (row) => <Badge variant="outline">{row.status_label}</Badge>,
        priority: (row) => <Badge variant="outline">{row.priority_label}</Badge>,
        task_type: (row) => <TextCell value={row.task_type_label} muted />,
        assignee: (row) => <TextCell value={row.assignee ?? 'Unassigned'} muted />,
        scheduled_for: (row) => <TextCell value={row.scheduled_for} muted />,
    };

    return (
        <AppLayout title="Housekeeping" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Housekeeping"
                    description="Room cleaning tasks — checkout cleans are created automatically."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <AiAssistButton
                                action="housekeeping.floor_readiness"
                                label="Floor readiness"
                                description="Summarise and prioritise open housekeeping tasks for this shift."
                            />
                            {can.create ? (
                                <Button asChild>
                                    <Link href={route('housekeeping.create')}>
                                        <Plus className="size-4" aria-hidden="true" />
                                        New task
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="housekeeping-tasks"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder="Search tasks…"
                    emptyState={<EmptyState icon={filtered ? SearchX : Brush} title={filtered ? 'No matches' : 'No tasks yet'} className="border-0" />}
                />
            </div>
        </AppLayout>
    );
}
