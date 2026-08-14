import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { BooleanCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import { AdminLayout } from '@/layouts/admin-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { Plan } from '@/types/billing';
import { router, useForm } from '@inertiajs/react';
import { Boxes, CircleAlert, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Amount } from '../../billing/billing-ui';

interface AdminPlansProps {
    table: TablePayload<Plan>;
    gateways: string[];
    /** Available feature flags, keyed by entitlement key → human label. */
    entitlements: Record<string, string>;
    can: { manage: boolean };
}

interface PlanFormValues {
    name: string;
    slug: string;
    description: string;
    monthly_price: string;
    yearly_price: string;
    currency: string;
    trial_days: string;
    sort: string;
    is_active: boolean;
    is_public: boolean;
    features: string[];
    entitlements: string[];
    [key: string]: string | boolean | string[];
}

/** Minor units back to the major-unit string the form edits. */
function toMajor(minor: number): string {
    return (minor / 100).toFixed(2);
}

function emptyValues(): PlanFormValues {
    return {
        name: '',
        slug: '',
        description: '',
        monthly_price: '0.00',
        yearly_price: '0.00',
        currency: 'USD',
        trial_days: '0',
        sort: '0',
        is_active: true,
        is_public: true,
        features: [],
        entitlements: [],
    };
}

export default function AdminPlans({ table, gateways, entitlements, can }: AdminPlansProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const [editing, setEditing] = useState<Plan | null>(null);
    const [open, setOpen] = useState(false);

    const form = useForm<PlanFormValues>(emptyValues());

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Platform', href: routeUrl('admin.dashboard') ?? undefined },
        { label: 'Plans' },
    ];

    const mayManage = can.manage && allows('billing.plans.manage');

    function openCreate(): void {
        setEditing(null);
        form.setDefaults(emptyValues());
        form.reset();
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(plan: Plan): void {
        setEditing(plan);
        form.clearErrors();
        form.setData({
            name: plan.name,
            slug: plan.slug,
            description: plan.description ?? '',
            monthly_price: toMajor(plan.monthly_price),
            yearly_price: toMajor(plan.yearly_price),
            currency: plan.currency,
            trial_days: String(plan.trial_days),
            sort: String(plan.sort),
            is_active: plan.is_active,
            is_public: plan.is_public,
            features: plan.features,
            entitlements: plan.entitlements ?? [],
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
            // No gateway_prices key: an omitted field means "leave unchanged",
            // and this form does not edit the price matrix.
            form.put(route('admin.plans.update', editing.slug), options);

            return;
        }

        form.post(route('admin.plans.store'), options);
    }

    async function remove(plan: Plan): Promise<void> {
        const ok = await confirm({
            title: `Delete the ${plan.name} plan?`,
            description: 'Plans with subscribers cannot be deleted — deactivate them instead so nobody new can pick them.',
            variant: 'destructive',
            confirmLabel: 'Delete plan',
        });

        if (ok) {
            router.delete(route('admin.plans.destroy', plan.slug), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<Plan> = {
        name: (row) => (
            <div className="min-w-0">
                <span className="block truncate text-sm font-medium">{row.name}</span>
                <span className="block truncate text-xs text-muted-foreground">{row.slug}</span>
            </div>
        ),
        monthly_price: (row) => <Amount value={row.monthly_price_formatted} className="text-sm" />,
        yearly_price: (row) => <Amount value={row.yearly_price_formatted} className="text-sm" />,
        trial_days: (row) => <span className="tabular-nums">{row.trial_days > 0 ? `${row.trial_days} days` : '—'}</span>,
        subscribers_count: (row) => <span className="tabular-nums">{row.subscribers_count ?? 0}</span>,
        is_active: (row) => <BooleanCell value={row.is_active} trueLabel="Active" falseLabel="Inactive" />,
        sort: (row) => <span className="tabular-nums">{row.sort}</span>,
    };

    function rowActions(row: Plan): RowAction[] {
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
                disabled: (row.subscribers_count ?? 0) > 0,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            },
        ];
    }

    return (
        <AdminLayout title="Plans" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Plans"
                    description="The catalogue every workspace subscribes from."
                    actions={
                        mayManage ? (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" aria-hidden="true" />
                                New plan
                            </Button>
                        ) : undefined
                    }
                />

                <DataTable<Plan>
                    payload={table}
                    propKey="table"
                    name="plans"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search plans…"
                    caption="Sellable plans"
                    emptyState={
                        <EmptyState
                            icon={Boxes}
                            title="No plans yet"
                            description="Create the first plan to start selling."
                            className="border-0"
                            action={
                                mayManage ? (
                                    <Button size="sm" onClick={openCreate}>
                                        <Plus className="size-4" aria-hidden="true" />
                                        New plan
                                    </Button>
                                ) : null
                            }
                        />
                    }
                />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <form noValidate onSubmit={submit} className="space-y-5">
                        <DialogHeader>
                            <DialogTitle>{editing ? `Edit ${editing.name}` : 'New plan'}</DialogTitle>
                            <DialogDescription>
                                Prices are entered in major units &mdash; &ldquo;19.99&rdquo; &mdash; and stored as integer minor units.
                            </DialogDescription>
                        </DialogHeader>

                        {Object.keys(form.errors).length > 0 && (
                            <p className="flex items-start gap-1.5 text-sm text-destructive" role="alert">
                                <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                Review the highlighted fields and try again.
                            </p>
                        )}

                        <ScrollArea className="max-h-[60svh] pr-3">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id="plan-name" label="Name" error={form.errors.name} required className="sm:col-span-2">
                                    <Input
                                        id="plan-name"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        required
                                        aria-invalid={form.errors.name ? true : undefined}
                                    />
                                </Field>

                                <Field id="plan-slug" label="Slug" error={form.errors.slug} hint="Leave empty to derive it from the name.">
                                    <Input
                                        id="plan-slug"
                                        value={form.data.slug}
                                        onChange={(event) => form.setData('slug', event.target.value)}
                                        aria-invalid={form.errors.slug ? true : undefined}
                                    />
                                </Field>

                                <Field id="plan-currency" label="Currency" error={form.errors.currency} required>
                                    <Input
                                        id="plan-currency"
                                        value={form.data.currency}
                                        onChange={(event) => form.setData('currency', event.target.value.toUpperCase())}
                                        maxLength={3}
                                        required
                                        aria-invalid={form.errors.currency ? true : undefined}
                                    />
                                </Field>

                                <Field id="plan-monthly" label="Monthly price" error={form.errors.monthly_price} required>
                                    <Input
                                        id="plan-monthly"
                                        inputMode="decimal"
                                        value={form.data.monthly_price}
                                        onChange={(event) => form.setData('monthly_price', event.target.value)}
                                        required
                                        aria-invalid={form.errors.monthly_price ? true : undefined}
                                    />
                                </Field>

                                <Field id="plan-yearly" label="Yearly price" error={form.errors.yearly_price} required>
                                    <Input
                                        id="plan-yearly"
                                        inputMode="decimal"
                                        value={form.data.yearly_price}
                                        onChange={(event) => form.setData('yearly_price', event.target.value)}
                                        required
                                        aria-invalid={form.errors.yearly_price ? true : undefined}
                                    />
                                </Field>

                                <Field id="plan-trial" label="Trial days" error={form.errors.trial_days} required>
                                    <Input
                                        id="plan-trial"
                                        inputMode="numeric"
                                        value={form.data.trial_days}
                                        onChange={(event) => form.setData('trial_days', event.target.value)}
                                        required
                                        aria-invalid={form.errors.trial_days ? true : undefined}
                                    />
                                </Field>

                                <Field id="plan-sort" label="Display order" error={form.errors.sort}>
                                    <Input
                                        id="plan-sort"
                                        inputMode="numeric"
                                        value={form.data.sort}
                                        onChange={(event) => form.setData('sort', event.target.value)}
                                        aria-invalid={form.errors.sort ? true : undefined}
                                    />
                                </Field>

                                <Field id="plan-description" label="Description" error={form.errors.description} className="sm:col-span-2">
                                    <Textarea
                                        id="plan-description"
                                        rows={2}
                                        value={form.data.description}
                                        onChange={(event) => form.setData('description', event.target.value)}
                                    />
                                </Field>

                                <Field
                                    id="plan-features"
                                    label="Features"
                                    error={form.errors.features}
                                    hint="One per line. These are the bullet points on the plan picker."
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="plan-features"
                                        rows={4}
                                        value={form.data.features.join('\n')}
                                        onChange={(event) =>
                                            form.setData(
                                                'features',
                                                event.target.value.split('\n').map((line) => line.trimStart()),
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    id="plan-entitlements"
                                    label="Included features"
                                    error={form.errors.entitlements}
                                    hint="Modules this plan unlocks. Gated on the server, not just hidden."
                                    className="sm:col-span-2"
                                >
                                    <div className="grid grid-cols-2 gap-2">
                                        {Object.entries(entitlements).map(([key, label]) => {
                                            const checked = form.data.entitlements.includes(key);

                                            return (
                                                <label
                                                    key={key}
                                                    className="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        className="size-4"
                                                        checked={checked}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'entitlements',
                                                                event.target.checked
                                                                    ? [...form.data.entitlements, key]
                                                                    : form.data.entitlements.filter((value) => value !== key),
                                                            )
                                                        }
                                                    />
                                                    {label}
                                                </label>
                                            );
                                        })}
                                    </div>
                                </Field>

                                <div className="flex items-center justify-between gap-3 sm:col-span-2">
                                    <Label htmlFor="plan-active" className="flex-1">
                                        Active
                                        <span className="block text-xs font-normal text-muted-foreground">
                                            Inactive plans cannot be subscribed to.
                                        </span>
                                    </Label>
                                    <Switch
                                        id="plan-active"
                                        checked={form.data.is_active}
                                        onCheckedChange={(checked) => form.setData('is_active', checked)}
                                    />
                                </div>

                                <div className="flex items-center justify-between gap-3 sm:col-span-2">
                                    <Label htmlFor="plan-public" className="flex-1">
                                        Public
                                        <span className="block text-xs font-normal text-muted-foreground">
                                            Hidden plans stay available to existing subscribers.
                                        </span>
                                    </Label>
                                    <Switch
                                        id="plan-public"
                                        checked={form.data.is_public}
                                        onCheckedChange={(checked) => form.setData('is_public', checked)}
                                    />
                                </div>

                                {editing && gateways.length > 0 && (
                                    <div className="space-y-2 sm:col-span-2">
                                        <p className="text-xs text-muted-foreground">Gateway price identifiers</p>
                                        <div className="flex flex-wrap gap-2">
                                            {gateways.map((name) => {
                                                const configured = Object.keys(editing.gateway_prices[name] ?? {}).length;

                                                return (
                                                    <Badge key={name} variant={configured > 0 ? 'success' : 'outline'}>
                                                        {name}: {configured > 0 ? `${configured} configured` : 'not configured'}
                                                    </Badge>
                                                );
                                            })}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Edited outside this form, and left untouched when you save here.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </ScrollArea>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                {editing ? 'Save plan' : 'Create plan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

function Field({
    id,
    label,
    error,
    hint,
    required = false,
    className,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={['space-y-2', className].filter(Boolean).join(' ')}>
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                )}
            </Label>
            {children}
            {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && (
                <p className="flex items-start gap-1.5 text-sm text-destructive">
                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    {error}
                </p>
            )}
        </div>
    );
}
