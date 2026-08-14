import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Invoice, NextInvoice, PaymentMethod, Subscription, UsageMeter } from '@/types/billing';
import { Link, router } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { ArrowUpRight, CalendarClock, CreditCard, Gauge, Info, Receipt, RotateCcw, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Amount, StatusBadge, UsageMeterBar } from './billing-ui';

interface BillingIndexProps {
    subscription: Subscription | null;
    meters: UsageMeter[];
    invoices: Invoice[];
    next_invoice: NextInvoice | null;
    payment_method: PaymentMethod | null;
    gateway: string;
    billing_enabled: boolean;
    can: {
        subscribe: boolean;
        cancel: boolean;
        manage_payment_methods: boolean;
        download_invoices: boolean;
    };
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PP') : '—';
}

export default function BillingIndex({
    subscription,
    meters,
    invoices,
    next_invoice: nextInvoice,
    payment_method: paymentMethod,
    gateway,
    billing_enabled: billingEnabled,
    can,
}: BillingIndexProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const [working, setWorking] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Billing' }];

    async function cancel(): Promise<void> {
        if (!subscription) {
            return;
        }

        const ok = await confirm({
            title: 'Cancel this subscription?',
            description: 'You keep full access until the end of the period you have already paid for. You can resume before then.',
            variant: 'destructive',
            confirmLabel: 'Cancel subscription',
        });

        if (!ok) {
            return;
        }

        setWorking(true);
        router.delete(route('billing.cancel', subscription.id), {
            preserveScroll: true,
            onFinish: () => setWorking(false),
        });
    }

    function resume(): void {
        if (!subscription) {
            return;
        }

        setWorking(true);
        router.post(route('billing.resume', subscription.id), {}, { preserveScroll: true, onFinish: () => setWorking(false) });
    }

    const choosePlan = allows('billing.subscribe') && can.subscribe && (
        <Button asChild>
            <Link href={route('billing.plans')}>
                <Sparkles className="size-4" aria-hidden="true" />
                {subscription ? 'Change plan' : 'Choose a plan'}
            </Link>
        </Button>
    );

    return (
        <AppLayout title="Billing" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Billing"
                    description="Your plan, what it includes, and what happens next."
                    actions={choosePlan || undefined}
                />

                {!billingEnabled && (
                    <Alert>
                        <Info className="size-4" aria-hidden="true" />
                        <AlertTitle>Billing is running in preview</AlertTitle>
                        <AlertDescription>
                            No payment processor is connected, so plan limits are not enforced and nothing is charged. Subscriptions and
                            invoices are still recorded through the <span className="font-medium">{gateway}</span> gateway.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <CardTitle>Current plan</CardTitle>
                                    <CardDescription>
                                        {subscription
                                            ? `Billed ${subscription.interval_label.toLowerCase()} through ${subscription.gateway}.`
                                            : 'This workspace is not on a paid plan.'}
                                    </CardDescription>
                                </div>
                                {subscription && <StatusBadge label={subscription.status_label} color={subscription.status_color} />}
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-5">
                            {!subscription ? (
                                <EmptyState
                                    icon={CreditCard}
                                    title="No active subscription"
                                    description="Pick a plan to unlock the paid features of this workspace."
                                    className="border-0"
                                    action={choosePlan || undefined}
                                />
                            ) : (
                                <>
                                    <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        <span className="text-2xl font-semibold tracking-tight">{subscription.plan?.name}</span>
                                        {subscription.price_formatted && (
                                            <span className="text-muted-foreground">
                                                <Amount value={subscription.price_formatted} /> /{' '}
                                                {subscription.interval_label.toLowerCase()}
                                            </span>
                                        )}
                                    </div>

                                    {subscription.plan?.description && (
                                        <p className="text-sm text-muted-foreground">{subscription.plan.description}</p>
                                    )}

                                    {subscription.on_trial && subscription.trial_ends_at && (
                                        <Alert>
                                            <CalendarClock className="size-4" aria-hidden="true" />
                                            <AlertTitle>Trial ends {formatDate(subscription.trial_ends_at)}</AlertTitle>
                                            <AlertDescription>
                                                Add a payment method before then and nothing about your workspace changes.
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {subscription.is_cancelling && subscription.cancels_at && (
                                        <Alert variant="warning">
                                            <CalendarClock className="size-4" aria-hidden="true" />
                                            <AlertTitle>Scheduled to end {formatDate(subscription.cancels_at)}</AlertTitle>
                                            <AlertDescription>
                                                You keep full access until then. Resume any time before that date.
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    <Separator />

                                    <dl className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-xs text-muted-foreground">Current period</dt>
                                            <dd className="text-sm">
                                                {formatDate(subscription.current_period_start)} –{' '}
                                                {formatDate(subscription.current_period_end)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-muted-foreground">Seats included</dt>
                                            <dd className="text-sm tabular-nums">
                                                {subscription.plan?.limits.seats === undefined || subscription.plan.limits.seats < 0
                                                    ? 'Unlimited'
                                                    : subscription.plan.limits.seats}
                                            </dd>
                                        </div>
                                    </dl>

                                    <div className="flex flex-wrap gap-2">
                                        {allows('billing.subscribe') && (
                                            <Button asChild variant="outline">
                                                <Link href={route('billing.plans')}>Change plan</Link>
                                            </Button>
                                        )}

                                        {subscription.is_cancelling
                                            ? allows('billing.subscribe') && (
                                                  <Button variant="secondary" onClick={resume} loading={working}>
                                                      <RotateCcw className="size-4" aria-hidden="true" />
                                                      Resume subscription
                                                  </Button>
                                              )
                                            : can.cancel && (
                                                  <Button variant="ghost" onClick={() => void cancel()} loading={working}>
                                                      Cancel subscription
                                                  </Button>
                                              )}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Next invoice</CardTitle>
                                <CardDescription>What renews, and when.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {nextInvoice ? (
                                    <div className="space-y-1">
                                        <p className="text-2xl font-semibold tracking-tight tabular-nums">{nextInvoice.amount_formatted}</p>
                                        <p className="text-sm text-muted-foreground">due {formatDate(nextInvoice.date)}</p>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">Nothing is scheduled to renew.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>Payment method</CardTitle>
                                        <CardDescription>Used for renewals.</CardDescription>
                                    </div>
                                    {can.manage_payment_methods && (
                                        <Button asChild variant="ghost" size="sm">
                                            <Link href={route('billing.payment-methods.index')}>
                                                Manage
                                                <ArrowUpRight className="size-4" aria-hidden="true" />
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent>
                                {paymentMethod ? (
                                    <div className="flex items-center gap-3">
                                        <CreditCard className="size-5 text-muted-foreground" aria-hidden="true" />
                                        <div className="min-w-0 text-sm">
                                            <p className="font-medium capitalize">
                                                {paymentMethod.brand ?? paymentMethod.type}
                                                {paymentMethod.last_four ? ` •••• ${paymentMethod.last_four}` : ''}
                                            </p>
                                            {paymentMethod.exp_month && paymentMethod.exp_year && (
                                                <p className="text-xs text-muted-foreground tabular-nums">
                                                    Expires {String(paymentMethod.exp_month).padStart(2, '0')}/{paymentMethod.exp_year}
                                                </p>
                                            )}
                                        </div>
                                        {paymentMethod.is_expired && <Badge variant="destructive">Expired</Badge>}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No payment method on file.</p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Usage this period</CardTitle>
                        <CardDescription>Measured against the allowances your plan includes.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {meters.length === 0 ? (
                            <EmptyState
                                icon={Gauge}
                                title="No metered allowances"
                                description="This plan does not cap anything, so there is nothing to track here."
                                className="border-0"
                            />
                        ) : (
                            <div className="grid gap-5 sm:grid-cols-2">
                                {meters.map((meter) => (
                                    <UsageMeterBar key={meter.key} meter={meter} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <CardTitle>Recent invoices</CardTitle>
                                <CardDescription>The last five, newest first.</CardDescription>
                            </div>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={route('billing.invoices.index')}>
                                    All invoices
                                    <ArrowUpRight className="size-4" aria-hidden="true" />
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {invoices.length === 0 ? (
                            <EmptyState
                                icon={Receipt}
                                title="No invoices yet"
                                description="Invoices appear here as soon as the first period is billed."
                                className="border-0"
                            />
                        ) : (
                            <ul className="divide-y divide-border">
                                {invoices.map((invoice) => (
                                    <li key={invoice.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">{invoice.number}</p>
                                            <p className="text-xs text-muted-foreground">{formatDate(invoice.issued_at)}</p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <StatusBadge label={invoice.status_label} color={invoice.status_color} />
                                            <Amount value={invoice.total_formatted} className="text-sm font-medium" />
                                            {can.download_invoices && (
                                                <Button asChild variant="ghost" size="sm">
                                                    <a href={route('billing.invoices.download', invoice.id)}>
                                                        <span className="sr-only">Download invoice {invoice.number}</span>
                                                        <Receipt className="size-4" aria-hidden="true" />
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
