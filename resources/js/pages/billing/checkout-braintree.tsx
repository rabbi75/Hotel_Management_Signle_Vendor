import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/*
|------------------------------------------------------------------------------
| Braintree checkout
|------------------------------------------------------------------------------
|
| Braintree has no hosted payment page: its drop-in renders in our own, against
| a client token minted server-side. So this is the one processor whose customer
| never leaves the application — the flow is otherwise identical, and the nonce
| the drop-in produces is posted to the same signed return URL every other
| gateway comes back to.
|
| The script comes from Braintree's CDN. The application sets no CSP of its own,
| but a deployment that adds one must allow js.braintreegateway.com.
|
*/

const SCRIPT_URL = 'https://js.braintreegateway.com/web/dropin/1.43.0/js/dropin.min.js';

interface BraintreeInstance {
    requestPaymentMethod: () => Promise<{ nonce: string }>;
}

interface BraintreeDropin {
    create: (options: { authorization: string; container: HTMLElement }) => Promise<BraintreeInstance>;
}

declare global {
    interface Window {
        braintree?: { dropin?: BraintreeDropin };
    }
}

interface BraintreeCheckoutProps {
    plan: { name: string; price: string };
    amount: number;
    currency: string;
    clientToken: string;
    returnUrl: string;
    cancelUrl: string;
}

export default function BraintreeCheckout({ plan, amount, currency, clientToken, returnUrl, cancelUrl }: BraintreeCheckoutProps) {
    const container = useRef<HTMLDivElement>(null);
    const instance = useRef<BraintreeInstance | null>(null);

    const [ready, setReady] = useState(false);
    const [failed, setFailed] = useState<string | null>(null);
    const [paying, setPaying] = useState(false);

    useEffect(() => {
        let cancelled = false;

        function mount(): void {
            const dropin = window.braintree?.dropin;

            if (!dropin || !container.current || cancelled) {
                return;
            }

            dropin
                .create({ authorization: clientToken, container: container.current })
                .then((created) => {
                    if (cancelled) {
                        return;
                    }

                    instance.current = created;
                    setReady(true);
                })
                .catch(() => setFailed('The card form could not be loaded.'));
        }

        if (window.braintree?.dropin) {
            mount();

            return () => {
                cancelled = true;
            };
        }

        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = mount;
        script.onerror = () => setFailed('The card form could not be reached. Check your connection and try again.');
        document.head.appendChild(script);

        return () => {
            cancelled = true;
        };
    }, [clientToken]);

    function pay(): void {
        if (!instance.current) {
            return;
        }

        setPaying(true);

        instance.current
            .requestPaymentMethod()
            .then(({ nonce }) => {
                // Straight to the signed return URL, exactly where a redirecting
                // processor would have sent the customer.
                router.get(returnUrl, { payment_method_nonce: nonce, amount, currency });
            })
            .catch(() => {
                setPaying(false);
                setFailed('Those card details were not accepted. Please check them and try again.');
            });
    }

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Billing' }, { label: 'Checkout' }];

    return (
        <AppLayout title="Checkout" breadcrumbs={breadcrumbs}>
            <div className="mx-auto w-full max-w-lg space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{plan.name}</CardTitle>
                        <CardDescription>
                            {plan.price} — enter your card details to complete the subscription.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {failed && (
                            <Alert variant="destructive">
                                <CircleAlert className="size-4" aria-hidden="true" />
                                <AlertTitle>Payment could not be taken</AlertTitle>
                                <AlertDescription>{failed}</AlertDescription>
                            </Alert>
                        )}

                        <div ref={container} />

                        {!ready && !failed && (
                            <div className="flex items-center justify-center py-8">
                                <Spinner />
                            </div>
                        )}

                        <div className="flex flex-wrap gap-2">
                            <Button type="button" disabled={!ready || paying} onClick={pay}>
                                {paying ? 'Processing…' : `Pay ${plan.price}`}
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => router.get(cancelUrl)}>
                                Cancel
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
