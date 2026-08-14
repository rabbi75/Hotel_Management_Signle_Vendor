import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { BillingIntervalValue, CheckoutGatewayOption, Plan, ProrationPreview, Subscription } from '@/types/billing';
import { useForm } from '@inertiajs/react';
import { Check, CircleAlert, Package, Tag } from 'lucide-react';
import { useState } from 'react';
import { Amount } from './billing-ui';

interface PlansPageProps {
    plans: Plan[];
    subscription: Subscription | null;
    /** Keyed `{slug}:{interval}`; empty when there is nothing to prorate against. */
    previews: Record<string, ProrationPreview>;
    /** Processors that can take money for each plan, keyed by plan slug. */
    gateways: Record<string, CheckoutGatewayOption[]>;
    can: { subscribe: boolean };
}

interface PlanFormValues {
    plan: string;
    interval: BillingIntervalValue;
    coupon: string;
    gateway: string;
    [key: string]: string;
}

export default function BillingPlans({ plans, subscription, previews, gateways, can }: PlansPageProps) {
    const { can: allows } = usePermissions();
    const [interval, setInterval] = useState<BillingIntervalValue>(subscription?.interval ?? 'monthly');
    const [pending, setPending] = useState<string | null>(null);

    const [gateway, setGateway] = useState<string>('');

    const form = useForm<PlanFormValues>({
        plan: '',
        interval: subscription?.interval ?? 'monthly',
        coupon: '',
        gateway: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Billing', href: routeUrl('billing.index') ?? undefined },
        { label: 'Plans' },
    ];

    const mayBuy = can.subscribe && allows('billing.subscribe');

    function choose(plan: Plan): void {
        setPending(plan.slug);

        // The picked processor is re-checked server-side against the same
        // rules this list was built from, so an edited field cannot reach a
        // gateway that was never on offer.
        const offered = gateways[plan.slug] ?? [];
        const chosen = offered.some((option) => option.driver === gateway) ? gateway : (offered[0]?.driver ?? '');

        form.transform((values) => ({ ...values, plan: plan.slug, interval, gateway: chosen }));

        if (subscription) {
            form.put(route('billing.swap', subscription.id), { onFinish: () => setPending(null) });

            return;
        }

        form.post(route('billing.subscribe'), { onFinish: () => setPending(null) });
    }

    return (
        <AppLayout title="Plans" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Plans"
                    description={
                        subscription
                            ? 'Change plan at any time. The difference for the rest of this period lands on your next invoice.'
                            : 'Pick the plan that fits. You can change it later.'
                    }
                />

                {Object.keys(form.errors).length > 0 && (
                    <Alert variant="destructive">
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>This plan could not be selected</AlertTitle>
                        <AlertDescription>{Object.values(form.errors)[0]}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-2">
                        <Label id="interval-label">Billing period</Label>
                        <ToggleGroup
                            type="single"
                            value={interval}
                            onValueChange={(value) => value && setInterval(value as BillingIntervalValue)}
                            aria-labelledby="interval-label"
                            variant="outline"
                        >
                            <ToggleGroupItem value="monthly">Monthly</ToggleGroupItem>
                            <ToggleGroupItem value="yearly">
                                Yearly
                                <Badge variant="success" className="ml-2">
                                    Save
                                </Badge>
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    {mayBuy && !subscription && (
                        <div className="w-full space-y-2 sm:w-64">
                            <Label htmlFor="coupon">Coupon code</Label>
                            <div className="relative">
                                <Tag
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="coupon"
                                    value={form.data.coupon}
                                    onChange={(event) => form.setData('coupon', event.target.value.toUpperCase())}
                                    placeholder="LAUNCH20"
                                    className="pl-9"
                                    aria-invalid={form.errors.coupon ? true : undefined}
                                />
                            </div>
                        </div>
                    )}
                </div>

                {/*
                    Only shown when there is a genuine choice. One processor
                    needs no picker, and none at all is a configuration problem
                    the customer should be told about rather than discovering at
                    the payment step.
                */}
                {mayBuy && !subscription && (() => {
                    const options = Object.values(gateways).flat();
                    const distinct = [...new Map(options.map((option) => [option.driver, option])).values()];

                    if (distinct.length === 0) {
                        return (
                            <Alert variant="destructive">
                                <CircleAlert className="size-4" aria-hidden="true" />
                                <AlertTitle>No payment method is available</AlertTitle>
                                <AlertDescription>
                                    No configured processor can take payment in this currency. Please contact support.
                                </AlertDescription>
                            </Alert>
                        );
                    }

                    if (distinct.length === 1) {
                        return null;
                    }

                    return (
                        <div className="space-y-2">
                            <Label id="gateway-label">Pay with</Label>
                            <ToggleGroup
                                type="single"
                                value={gateway || distinct[0]?.driver}
                                onValueChange={(value) => value && setGateway(value)}
                                aria-labelledby="gateway-label"
                                variant="outline"
                                className="flex-wrap justify-start"
                            >
                                {distinct.map((option) => (
                                    <ToggleGroupItem key={option.driver} value={option.driver}>
                                        {option.label}
                                        {option.test_mode && (
                                            <Badge variant="warning" className="ml-2">
                                                Test
                                            </Badge>
                                        )}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                        </div>
                    );
                })()}

                {plans.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="No plans are available"
                        description="An administrator has not published any plans yet."
                    />
                ) : (
                    <ul className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        {plans.map((plan) => {
                            const current = subscription?.plan_id === plan.id && subscription.interval === interval;
                            const preview = previews[`${plan.slug}:${interval}`];
                            const price = interval === 'yearly' ? plan.yearly_price_formatted : plan.monthly_price_formatted;

                            return (
                                <li key={plan.id}>
                                    <Card className={cn('h-full', current && 'border-primary ring-1 ring-primary')}>
                                        <CardHeader>
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <CardTitle>{plan.name}</CardTitle>
                                                    {plan.description && <CardDescription>{plan.description}</CardDescription>}
                                                </div>
                                                {current && <Badge>Current</Badge>}
                                            </div>
                                        </CardHeader>

                                        <CardContent className="flex h-full flex-col gap-5">
                                            <div>
                                                <p className="text-3xl font-semibold tracking-tight">
                                                    <Amount value={price} />
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    per {interval === 'yearly' ? 'year' : 'month'}
                                                    {plan.trial_days > 0 && ` · ${plan.trial_days}-day free trial`}
                                                </p>
                                                {interval === 'yearly' && plan.yearly_savings > 0 && (
                                                    <p className="mt-1 text-sm text-success">
                                                        Save <Amount value={plan.yearly_savings_formatted} /> a year
                                                    </p>
                                                )}
                                            </div>

                                            {plan.features.length > 0 && (
                                                <ul className="space-y-2 text-sm">
                                                    {plan.features.map((feature) => (
                                                        <li key={feature} className="flex items-start gap-2">
                                                            <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />
                                                            <span>{feature}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}

                                            {preview && !current && preview.prorated && (
                                                <p className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                                    Switching now costs <Amount value={preview.due_formatted} className="font-medium" /> for
                                                    the remainder of this period.
                                                </p>
                                            )}

                                            <div className="mt-auto pt-2">
                                                <Button
                                                    className="w-full"
                                                    variant={current ? 'outline' : 'default'}
                                                    disabled={!mayBuy || current}
                                                    loading={pending === plan.slug && form.processing}
                                                    onClick={() => choose(plan)}
                                                >
                                                    {current
                                                        ? 'Your current plan'
                                                        : subscription
                                                          ? `Switch to ${plan.name}`
                                                          : `Choose ${plan.name}`}
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
