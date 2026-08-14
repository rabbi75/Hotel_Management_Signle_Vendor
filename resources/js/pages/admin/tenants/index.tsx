import { PageHeader } from '@/components/app-shell/page-header';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { AvatarCell, DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { AdminLayout } from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import type { TablePayload } from '@/types';
import type { TenantRow } from '@/types/admin';
import { router } from '@inertiajs/react';
import { Building2, CreditCard, Eye, LogIn } from 'lucide-react';
import { useMemo, useState } from 'react';

interface TenantsIndexProps {
    table: TablePayload<TenantRow>;
    plans: Record<string, string>;
    can: { manage: boolean; impersonate: boolean };
}

const STATUS_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    active: 'success',
    trialing: 'info',
    past_due: 'warning',
    canceled: 'destructive',
    expired: 'destructive',
    incomplete: 'secondary',
};

const columns: ColumnRenderers<TenantRow> = {
    name: (row) => <AvatarCell name={row.name} subtitle={row.owner?.email} src={row.logo} initials={row.initials} />,
    owner: (row) => <TextCell value={row.owner?.name ?? '—'} muted={!row.owner} />,
    plan: (row) => (row.plan ? <Badge variant="secondary">{row.plan}</Badge> : <Badge variant="outline">No plan</Badge>),
    subscription_status: (row) =>
        row.subscription_status ? (
            <Badge variant={STATUS_VARIANT[row.subscription_status] ?? 'secondary'}>{row.subscription_status.replace('_', ' ')}</Badge>
        ) : (
            <span className="text-muted-foreground">—</span>
        ),
    members_count: (row) => <TextCell value={row.members_count ?? 0} />,
    is_active: (row) =>
        row.is_active ? <Badge variant="success">Active</Badge> : <Badge variant="destructive">Suspended</Badge>,
    created_at: (row) => <DateCell value={row.created_at} />,
};

export default function TenantsIndex({ table, plans, can }: TenantsIndexProps) {
    const [assignTarget, setAssignTarget] = useState<TenantRow | null>(null);
    const [plan, setPlan] = useState('');
    const [interval, setInterval] = useState('monthly');
    const [assigning, setAssigning] = useState(false);

    const planEntries = useMemo(() => Object.entries(plans), [plans]);

    function openAssign(row: TenantRow): void {
        setAssignTarget(row);
        setPlan(row.plan_slug ?? planEntries[0]?.[0] ?? '');
        setInterval(row.subscription_interval ?? 'monthly');
    }

    function closeAssign(): void {
        if (assigning) {
            return;
        }

        setAssignTarget(null);
    }

    function submitAssign(): void {
        if (!assignTarget || !plan) {
            return;
        }

        setAssigning(true);

        router.put(
            route('admin.tenants.subscription.update', assignTarget.uuid),
            { plan, interval },
            {
                preserveScroll: true,
                onFinish: () => setAssigning(false),
                onSuccess: () => setAssignTarget(null),
            },
        );
    }

    function impersonate(row: TenantRow): void {
        router.post(route('admin.tenants.impersonate', row.uuid));
    }

    function rowActions(row: TenantRow): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'view',
                label: 'View tenant',
                icon: <Eye className="size-4" aria-hidden="true" />,
                onSelect: () => router.visit(route('admin.tenants.show', row.uuid)),
            },
        ];

        if (can.manage) {
            actions.push({
                id: 'assign-plan',
                label: 'Assign plan',
                icon: <CreditCard className="size-4" aria-hidden="true" />,
                onSelect: () => openAssign(row),
            });
        }

        if (can.impersonate && row.is_active && row.owner) {
            actions.push({
                id: 'impersonate',
                label: 'Login as client',
                icon: <LogIn className="size-4" aria-hidden="true" />,
                separatorBefore: true,
                onSelect: () => impersonate(row),
            });
        }

        return actions;
    }

    return (
        <AdminLayout title="Tenants" breadcrumbs={[{ label: 'Tenants' }]}>
            <div className="space-y-6">
                <PageHeader title="Tenants" description="Every workspace in the installation." />

                <DataTable
                    payload={table}
                    propKey="table"
                    columns={columns}
                    searchPlaceholder="Search workspaces…"
                    rowActions={rowActions}
                    onRowClick={(row) => router.visit(route('admin.tenants.show', row.uuid))}
                    emptyState={<EmptyState icon={Building2} title="No tenants" description="No workspaces match these filters." />}
                />
            </div>

            <Dialog open={assignTarget !== null} onOpenChange={(open) => !open && closeAssign()}>
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                    <DialogHeader className="space-y-3 border-b border-border px-6 py-5 text-left">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-primary/10 p-2.5 text-primary">
                                <CreditCard className="size-5" aria-hidden="true" />
                            </div>
                            <div className="min-w-0">
                                <DialogTitle>Assign plan</DialogTitle>
                                <DialogDescription className="mt-0.5">
                                    Update billing entitlements for this tenant.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="space-y-5 px-6 py-5">
                        {assignTarget && (
                            <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/40 px-3 py-3">
                                <Avatar className="size-10 rounded-md">
                                    {assignTarget.logo && <AvatarImage src={assignTarget.logo} alt="" />}
                                    <AvatarFallback className="rounded-md text-xs font-semibold">
                                        {assignTarget.initials}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{assignTarget.name}</p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {assignTarget.owner?.email ?? assignTarget.email ?? 'No contact email'}
                                    </p>
                                </div>
                                {assignTarget.plan ? (
                                    <Badge variant="secondary">{assignTarget.plan}</Badge>
                                ) : (
                                    <Badge variant="outline">No plan</Badge>
                                )}
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="tenant-plan">Plan</Label>
                            <Select value={plan} onValueChange={setPlan}>
                                <SelectTrigger id="tenant-plan" className="h-10 w-full">
                                    <SelectValue placeholder="Choose a plan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {planEntries.map(([slug, name]) => (
                                        <SelectItem key={slug} value={slug}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label>Billing interval</Label>
                            <div className="grid grid-cols-2 gap-2">
                                {[
                                    { value: 'monthly', label: 'Monthly' },
                                    { value: 'yearly', label: 'Yearly' },
                                ].map((option) => {
                                    const selected = interval === option.value;

                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() => setInterval(option.value)}
                                            className={cn(
                                                'rounded-lg border px-3 py-2.5 text-sm font-medium transition-colors',
                                                selected
                                                    ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary/30'
                                                    : 'border-border bg-background text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                            )}
                                        >
                                            {option.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Changes apply immediately through the manual gateway. No payment is collected from this
                            screen.
                        </p>
                    </div>

                    <Separator />

                    <DialogFooter className="gap-2 bg-muted/20 px-6 py-4 sm:justify-end">
                        <Button type="button" variant="outline" onClick={closeAssign} disabled={assigning}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={submitAssign} disabled={!plan || assigning}>
                            {assigning ? 'Applying…' : 'Apply plan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
