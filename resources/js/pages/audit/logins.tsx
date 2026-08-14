import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { BooleanCell, RelativeDateCell, TextCell } from '@/components/data-table/data-table-cells';
import type { ExportFormat } from '@/components/data-table/data-table-view-options';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { LoginRow } from '@/types/audit';
import { format, isValid, parseISO } from 'date-fns';
import { KeyRound, SearchX } from 'lucide-react';
import { useState } from 'react';
import { DetailRow, auditExportUrl } from './json-view';

interface LoginHistoryPageProps {
    table: TablePayload<LoginRow>;
}

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PPpp') : '—';
}

export default function AuditLogins({ table }: LoginHistoryPageProps) {
    const { can } = usePermissions();
    const [selected, setSelected] = useState<LoginRow | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Audit' },
        { label: 'Login history' },
    ];

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<LoginRow> = {
        email: (row) => <TextCell value={row.email} className="font-medium" />,
        user: (row) => <TextCell value={row.user} />,
        ip_address: (row) => <TextCell value={row.ip_address} muted className="font-mono text-xs" />,
        device_type: (row) => <TextCell value={row.device_type} />,
        platform: (row) => <TextCell value={row.platform} />,
        browser: (row) => <TextCell value={row.browser} />,
        successful: (row) => <Badge variant={row.successful ? 'success' : 'destructive'}>{row.successful ? 'Success' : 'Failed'}</Badge>,
        failure_reason: (row) => <TextCell value={row.failure_reason} muted />,
        two_factor_used: (row) => <BooleanCell value={row.two_factor_used} trueLabel="Used" falseLabel="Not used" />,
        logged_in_at: (row) => <RelativeDateCell value={row.logged_in_at} />,
        logged_out_at: (row) => <RelativeDateCell value={row.logged_out_at} />,
    };

    function onExport(exportFormat: ExportFormat): void {
        if (exportFormat === 'print') {
            window.print();

            return;
        }

        window.location.assign(auditExportUrl(route('audit.logins.export'), exportFormat === 'excel' ? 'xlsx' : 'csv'));
    }

    return (
        <AppLayout title="Login history" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="Login history" description="Successful and failed authentication attempts across the application." />

                <DataTable<LoginRow>
                    payload={table}
                    propKey="table"
                    name="logins"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    onRowClick={(row) => setSelected(row)}
                    primaryColumn="email"
                    searchPlaceholder="Search by email, name or IP…"
                    caption="Authentication attempts"
                    {...(can('audit.export') ? { onExport } : {})}
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No attempts match these filters"
                                description="Widen the date range or clear the result filter."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={KeyRound}
                                title="No sign-ins recorded yet"
                                description="Every attempt — successful or not — is recorded here."
                                className="border-0"
                            />
                        )
                    }
                />
            </div>

            <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Sign-in attempt</SheetTitle>
                        <SheetDescription>{selected?.email}</SheetDescription>
                    </SheetHeader>

                    {selected && (
                        <div className="px-6 pb-6">
                            <dl className="divide-y divide-border">
                                <DetailRow label="Result">
                                    <Badge variant={selected.successful ? 'success' : 'destructive'}>
                                        {selected.successful ? 'Success' : 'Failed'}
                                    </Badge>
                                </DetailRow>
                                <DetailRow label="Account">{selected.user ?? 'No matching account'}</DetailRow>
                                <DetailRow label="Failure reason">{selected.failure_reason ?? '—'}</DetailRow>
                                <DetailRow label="Two-factor">{selected.two_factor_used ? 'Used' : 'Not used'}</DetailRow>
                                <DetailRow label="IP address">
                                    <span className="font-mono text-xs">{selected.ip_address ?? '—'}</span>
                                </DetailRow>
                                <DetailRow label="Device">{selected.device_type ?? '—'}</DetailRow>
                                <DetailRow label="Platform">{selected.platform ?? '—'}</DetailRow>
                                <DetailRow label="Browser">{selected.browser ?? '—'}</DetailRow>
                                <DetailRow label="Signed in">{absolute(selected.logged_in_at)}</DetailRow>
                                <DetailRow label="Signed out">{absolute(selected.logged_out_at)}</DetailRow>
                            </dl>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
