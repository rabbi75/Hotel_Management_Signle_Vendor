import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaymentMethod } from '@/types/billing';
import { router, useForm } from '@inertiajs/react';
import { CircleAlert, CreditCard, Plus, ShieldCheck, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface PaymentMethodsPageProps {
    methods: PaymentMethod[];
    gateway: string;
    can: { manage: boolean };
}

interface TokenFormValues {
    token: string;
    make_default: boolean;
    [key: string]: string | boolean;
}

export default function PaymentMethods({ methods, gateway, can }: PaymentMethodsPageProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const [open, setOpen] = useState(false);

    const form = useForm<TokenFormValues>({ token: '', make_default: true });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Billing', href: routeUrl('billing.index') ?? undefined },
        { label: 'Payment methods' },
    ];

    const mayManage = can.manage && allows('billing.payment_methods.manage');

    async function remove(method: PaymentMethod): Promise<void> {
        const ok = await confirm({
            title: 'Remove this payment method?',
            description: method.is_default
                ? 'It is the default. Another method on file will be promoted, and renewals may fail if none remains.'
                : 'Renewals will continue on the default method.',
            variant: 'destructive',
            confirmLabel: 'Remove',
        });

        if (ok) {
            router.delete(route('billing.payment-methods.destroy', method.id), { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Payment methods" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Payment methods"
                    description="Cards and accounts used to renew your subscription."
                    actions={
                        mayManage ? (
                            <Button onClick={() => setOpen(true)}>
                                <Plus className="size-4" aria-hidden="true" />
                                Add payment method
                            </Button>
                        ) : undefined
                    }
                />

                <Alert>
                    <ShieldCheck className="size-4" aria-hidden="true" />
                    <AlertTitle>Card details never reach this application</AlertTitle>
                    <AlertDescription>
                        The <span className="font-medium">{gateway}</span> gateway issues a token in the browser; only that token, the brand
                        and the last four digits are ever stored here.
                    </AlertDescription>
                </Alert>

                {methods.length === 0 ? (
                    <EmptyState
                        icon={CreditCard}
                        title="No payment methods"
                        description="Add one so renewals do not interrupt your workspace."
                        action={
                            mayManage ? (
                                <Button size="sm" onClick={() => setOpen(true)}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    Add payment method
                                </Button>
                            ) : null
                        }
                    />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2">
                        {methods.map((method) => (
                            <li key={method.id}>
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <CardTitle className="capitalize">
                                                    {method.brand ?? method.type}
                                                    {method.last_four ? ` •••• ${method.last_four}` : ''}
                                                </CardTitle>
                                                <CardDescription>
                                                    {method.exp_month && method.exp_year
                                                        ? `Expires ${String(method.exp_month).padStart(2, '0')}/${method.exp_year}`
                                                        : `Held by ${method.gateway}`}
                                                </CardDescription>
                                            </div>
                                            <div className="flex shrink-0 flex-wrap gap-1">
                                                {method.is_default && <Badge>Default</Badge>}
                                                {method.is_expired && <Badge variant="destructive">Expired</Badge>}
                                            </div>
                                        </div>
                                    </CardHeader>

                                    {mayManage && (
                                        <CardContent className="flex flex-wrap gap-2">
                                            {!method.is_default && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.patch(
                                                            route('billing.payment-methods.default', method.id),
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <Star className="size-4" aria-hidden="true" />
                                                    Make default
                                                </Button>
                                            )}
                                            <Button variant="ghost" size="sm" onClick={() => void remove(method)}>
                                                <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                                                Remove
                                            </Button>
                                        </CardContent>
                                    )}
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);

                    if (!next) {
                        form.reset();
                        form.clearErrors();
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <form
                        noValidate
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(route('billing.payment-methods.store'), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset();
                                    setOpen(false);
                                },
                            });
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>Add a payment method</DialogTitle>
                            <DialogDescription>
                                Paste the token your gateway&rsquo;s card form produced. This screen never accepts a card number.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-2">
                            <Label htmlFor="pm-token">
                                Gateway token
                                <span className="text-destructive" aria-hidden="true">
                                    *
                                </span>
                            </Label>
                            <Input
                                id="pm-token"
                                value={form.data.token}
                                onChange={(event) => form.setData('token', event.target.value)}
                                autoComplete="off"
                                required
                                aria-invalid={form.errors.token ? true : undefined}
                                aria-describedby={form.errors.token ? 'pm-token-error' : undefined}
                                placeholder="pm_1NXxxxxxxxxxxxxx"
                            />
                            {form.errors.token && (
                                <p id="pm-token-error" className="flex items-start gap-1.5 text-sm text-destructive">
                                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    {form.errors.token}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                Add method
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
