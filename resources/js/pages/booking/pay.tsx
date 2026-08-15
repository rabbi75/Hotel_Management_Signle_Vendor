import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PublicCheckoutCart, PublicHotelSummary, PublicPaymentMethod } from '@/types/booking';
import type { SharedProps } from '@/types';
import type { PublicMenuNode } from '@/types/cms';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { PublicShell } from '@/pages/cms/public-shell';

function formatMoney(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}

interface Props {
    hotel: PublicHotelSummary;
    cart: PublicCheckoutCart;
    method: PublicPaymentMethod;
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

export default function BookingPay({ hotel, cart, method, menus }: Props) {
    const { errors } = usePage<SharedProps>().props;
    const form = useForm({ payment_reference: '' });

    return (
        <PublicShell header={menus.header} footer={menus.footer}>
            <Head title={`Pay with ${method.name}`} />

            <div className="mx-auto w-full max-w-xl px-4 py-16 sm:px-6">
                <p className="text-sm text-muted-foreground">{hotel.name}</p>
                <h1 className="mt-1 text-3xl font-semibold tracking-tight">Complete payment</h1>
                <p className="mt-3 text-muted-foreground">
                    Pay {formatMoney(cart.total, cart.currency)} with {method.name}. Your order is placed only after this
                    payment is recorded.
                </p>

                <div className="mt-8 space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <p className="text-sm font-medium">{method.name}</p>
                    <p className="text-sm text-muted-foreground whitespace-pre-wrap">{method.instructions}</p>
                    <p className="text-sm">
                        Stay: {cart.room_type} · {cart.check_in_date} → {cart.check_out_date}
                    </p>
                </div>

                {Object.keys(errors).length > 0 && (
                    <Alert variant="destructive" className="mt-6">
                        <CircleAlert />
                        <AlertTitle>Payment could not be confirmed</AlertTitle>
                        <AlertDescription>{Object.values(errors)[0]}</AlertDescription>
                    </Alert>
                )}

                <form
                    className="mt-6 space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('booking.pay.store', hotel.slug));
                    }}
                >
                    <div className="space-y-1.5">
                        <Label htmlFor="payment_reference">Transaction / receipt reference</Label>
                        <Input
                            id="payment_reference"
                            required
                            value={form.data.payment_reference}
                            onChange={(event) => form.setData('payment_reference', event.target.value)}
                            placeholder="e.g. TXN-48291"
                        />
                    </div>
                    <Button type="submit" className="w-full" disabled={form.processing}>
                        I have paid — place my order
                    </Button>
                    <Button asChild type="button" variant="outline" className="w-full">
                        <Link href={route('booking.checkout', hotel.slug)}>Choose another method</Link>
                    </Button>
                </form>
            </div>
        </PublicShell>
    );
}
