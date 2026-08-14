import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { BooleanCell, DateCell, EnumBadgeCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { usePermissions } from '@/hooks/use-permissions';
import { AdminLayout } from '@/layouts/admin-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { Coupon, CouponTypeValue, PlanOption } from '@/types/billing';
import { router, useForm } from '@inertiajs/react';
import { CircleAlert, Pencil, Plus, Tag, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface AdminCouponsProps {
    table: TablePayload<Coupon>;
    plans: PlanOption[];
    can: { manage: boolean };
}

interface CouponFormValues {
    code: string;
    description: string;
    type: CouponTypeValue;
    value: string;
    currency: string;
    max_redemptions: string;
    expires_at: string;
    plan_ids: number[];
    is_active: boolean;
    [key: string]: string | boolean | number[];
}

function emptyValues(): CouponFormValues {
    return {
        code: '',
        description: '',
        type: 'percent',
        value: '10',
        currency: 'USD',
        max_redemptions: '',
        expires_at: '',
        plan_ids: [],
        is_active: true,
    };
}

export default function AdminCoupons({ table, plans, can }: AdminCouponsProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const [editing, setEditing] = useState<Coupon | null>(null);
    const [open, setOpen] = useState(false);

    const form = useForm<CouponFormValues>(emptyValues());

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Platform', href: routeUrl('admin.dashboard') ?? undefined },
        { label: 'Coupons' },
    ];

    const mayManage = can.manage && allows('billing.coupons.manage');

    function openCreate(): void {
        setEditing(null);
        form.setDefaults(emptyValues());
        form.reset();
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(coupon: Coupon): void {
        setEditing(coupon);
        form.clearErrors();
        form.setData({
            code: coupon.code,
            description: coupon.description ?? '',
            type: coupon.type,
            value: coupon.type === 'percent' ? String(coupon.value) : (coupon.value / 100).toFixed(2),
            currency: coupon.currency ?? 'USD',
            max_redemptions: coupon.max_redemptions === null ? '' : String(coupon.max_redemptions),
            expires_at: coupon.expires_at ? coupon.expires_at.slice(0, 10) : '',
            plan_ids: coupon.plan_ids,
            is_active: coupon.is_active,
        });
        setOpen(true);
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
            },
        };

        if (editing) {
            form.put(route('admin.coupons.update', editing.code), options);

            return;
        }

        form.post(route('admin.coupons.store'), options);
    }

    async function remove(coupon: Coupon): Promise<void> {
        const ok = await confirm({
            title: `Delete ${coupon.code}?`,
            description:
                coupon.redeemed_count > 0
                    ? `It has been redeemed ${coupon.redeemed_count} time(s). Those redemptions stay on the invoices they discounted.`
                    : 'Nobody has redeemed this code yet.',
            variant: 'destructive',
            confirmLabel: 'Delete coupon',
        });

        if (ok) {
            router.delete(route('admin.coupons.destroy', coupon.code), { preserveScroll: true });
        }
    }

    function togglePlan(id: number, checked: boolean): void {
        form.setData('plan_ids', checked ? [...new Set([...form.data.plan_ids, id])] : form.data.plan_ids.filter((entry) => entry !== id));
    }

    const columns: ColumnRenderers<Coupon> = {
        code: (row) => (
            <div className="min-w-0">
                <span className="block truncate font-mono text-sm font-medium">{row.code}</span>
                {row.description && <span className="block truncate text-xs text-muted-foreground">{row.description}</span>}
            </div>
        ),
        type: (row) => (
            <EnumBadgeCell
                value={row.type}
                map={{ percent: { label: 'Percent', variant: 'info' }, fixed: { label: 'Fixed', variant: 'default' } }}
            />
        ),
        value_formatted: (row) => <span className="text-sm tabular-nums">{row.value_formatted}</span>,
        redeemed_count: (row) => <span className="tabular-nums">{row.redeemed_count}</span>,
        max_redemptions: (row) => <span className="tabular-nums">{row.max_redemptions ?? '∞'}</span>,
        expires_at: (row) =>
            row.expires_at ? (
                <span className="flex items-center gap-2">
                    <DateCell value={row.expires_at} />
                    {row.is_expired && <Badge variant="secondary">Expired</Badge>}
                </span>
            ) : (
                <span className="text-muted-foreground">Never</span>
            ),
        is_active: (row) => <BooleanCell value={row.is_active} trueLabel="Active" falseLabel="Inactive" />,
    };

    function rowActions(row: Coupon): RowAction[] {
        if (!mayManage) {
            return [];
        }

        return [
            {
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => openEdit(row),
            },
            {
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            },
        ];
    }

    return (
        <AdminLayout title="Coupons" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Coupons"
                    description="Discount codes customers can apply when they subscribe."
                    actions={
                        mayManage ? (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" aria-hidden="true" />
                                New coupon
                            </Button>
                        ) : undefined
                    }
                />

                <DataTable<Coupon>
                    payload={table}
                    propKey="table"
                    name="coupons"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search codes…"
                    caption="Discount codes"
                    emptyState={
                        <EmptyState
                            icon={Tag}
                            title="No coupons yet"
                            description="Create a code to run a promotion."
                            className="border-0"
                            action={
                                mayManage ? (
                                    <Button size="sm" onClick={openCreate}>
                                        <Plus className="size-4" aria-hidden="true" />
                                        New coupon
                                    </Button>
                                ) : null
                            }
                        />
                    }
                />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <form noValidate onSubmit={submit} className="space-y-5">
                        <DialogHeader>
                            <DialogTitle>{editing ? `Edit ${editing.code}` : 'New coupon'}</DialogTitle>
                            <DialogDescription>
                                A percentage coupon takes a whole percent off; a fixed coupon takes an amount, entered in major units.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="coupon-code">
                                    Code
                                    <span className="text-destructive" aria-hidden="true">
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="coupon-code"
                                    value={form.data.code}
                                    onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                                    required
                                    className="font-mono"
                                    aria-invalid={form.errors.code ? true : undefined}
                                    aria-describedby={form.errors.code ? 'coupon-code-error' : undefined}
                                />
                                {form.errors.code && (
                                    <p id="coupon-code-error" className="flex items-start gap-1.5 text-sm text-destructive">
                                        <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                        {form.errors.code}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="coupon-type">Type</Label>
                                <Select value={form.data.type} onValueChange={(value) => form.setData('type', value as CouponTypeValue)}>
                                    <SelectTrigger id="coupon-type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="percent">Percentage</SelectItem>
                                        <SelectItem value="fixed">Fixed amount</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="coupon-value">
                                    {form.data.type === 'percent' ? 'Percent off' : 'Amount off'}
                                    <span className="text-destructive" aria-hidden="true">
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="coupon-value"
                                    inputMode="decimal"
                                    value={form.data.value}
                                    onChange={(event) => form.setData('value', event.target.value)}
                                    required
                                    aria-invalid={form.errors.value ? true : undefined}
                                    aria-describedby={form.errors.value ? 'coupon-value-error' : undefined}
                                />
                                {form.errors.value && (
                                    <p id="coupon-value-error" className="flex items-start gap-1.5 text-sm text-destructive">
                                        <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                        {form.errors.value}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="coupon-limit">Redemption limit</Label>
                                <Input
                                    id="coupon-limit"
                                    inputMode="numeric"
                                    placeholder="Unlimited"
                                    value={form.data.max_redemptions}
                                    onChange={(event) => form.setData('max_redemptions', event.target.value)}
                                    aria-invalid={form.errors.max_redemptions ? true : undefined}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="coupon-expires">Expires on</Label>
                                <Input
                                    id="coupon-expires"
                                    type="date"
                                    value={form.data.expires_at}
                                    onChange={(event) => form.setData('expires_at', event.target.value)}
                                    aria-invalid={form.errors.expires_at ? true : undefined}
                                />
                            </div>

                            <fieldset className="space-y-2 sm:col-span-2">
                                <legend className="text-sm font-medium">Applies to</legend>
                                <p className="text-xs text-muted-foreground">Select none to allow the code on every plan.</p>
                                <div className="flex flex-wrap gap-3 pt-1">
                                    {plans.map((plan) => (
                                        <label key={plan.value} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={form.data.plan_ids.includes(plan.value)}
                                                onCheckedChange={(checked) => togglePlan(plan.value, checked === true)}
                                            />
                                            {plan.label}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>

                            <div className="flex items-center justify-between gap-3 sm:col-span-2">
                                <Label htmlFor="coupon-active" className="flex-1">
                                    Active
                                    <span className="block text-xs font-normal text-muted-foreground">
                                        Inactive codes are rejected at redemption.
                                    </span>
                                </Label>
                                <Switch
                                    id="coupon-active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) => form.setData('is_active', checked)}
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                {editing ? 'Save coupon' : 'Create coupon'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
