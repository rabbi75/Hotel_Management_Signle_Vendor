import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { SettingsLayout } from '@/layouts/settings-layout';
import type { SharedProps } from '@/types';
import type { LoginHistoryPageProps, LoginHistoryRow } from '@/types/auth';
import { usePage } from '@inertiajs/react';
import { History } from 'lucide-react';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';

function formatMoment(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

const COLUMNS: ColumnRenderers<LoginHistoryRow> = {
    logged_in_at: (row) => (
        <div className="min-w-0">
            <p className="truncate font-medium">{formatMoment(row.logged_in_at)}</p>
            <p className="text-xs text-muted-foreground">{row.logged_in_at_human}</p>
        </div>
    ),
    ip_address: (row) => <span className="font-mono text-xs">{row.ip_address ?? '—'}</span>,
    browser: (row) => row.browser ?? '—',
    platform: (row) => row.platform ?? '—',
    device_type: (row) => row.device_type ?? '—',
    successful: (row) => (
        <div className="flex flex-wrap items-center gap-1.5">
            <Badge variant={row.successful ? 'success' : 'destructive'}>{row.successful ? 'Success' : 'Failed'}</Badge>
            {row.is_current && <Badge variant="outline">This session</Badge>}
            {row.two_factor_used && <Badge variant="secondary">2FA</Badge>}
            {!row.successful && row.failure_reason && <span className="text-xs text-muted-foreground">{row.failure_reason}</span>}
        </div>
    ),
};

export default function LoginHistoryPage({ table, retention_days }: LoginHistoryPageProps) {
    const { errors } = usePage<SharedProps>().props;

    return (
        <SettingsLayout title="Sign-in history" description={`Every sign-in attempt on your account, kept for ${retention_days} days.`}>
            <ErrorSummary errors={errors} />

            <PanelCard title="Recent activity" description="Anything you do not recognise is worth changing your password over.">
                <DataTable<LoginHistoryRow>
                    payload={table}
                    propKey="table"
                    name="logins"
                    columns={COLUMNS}
                    getRowId={(row) => String(row.id)}
                    primaryColumn="logged_in_at"
                    searchPlaceholder="Search by IP, browser or platform"
                    caption="Sign-in history"
                    emptyState={
                        <EmptyState
                            icon={History}
                            title="No sign-ins recorded yet"
                            description="Your sign-in attempts will appear here as soon as they happen."
                        />
                    }
                />
            </PanelCard>
        </SettingsLayout>
    );
}
