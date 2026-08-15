import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
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
    methods: PublicPaymentMethod[];
    customer: { name: string; email: string } | null;
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

export default function BookingCheckout({ hotel, cart, methods, customer, menus }: Props) {
    const { errors } = usePage<SharedProps>().props;
    const defaultMethod = methods.find((method) => method.is_default)?.id ?? methods[0]?.id ?? '';

    const form = useForm({
        payment_method_id: String(defaultMethod),
        create_account: false,
        password: '',
        password_confirmation: '',
    });

    const selected = methods.find((method) => String(method.id) === form.data.payment_method_id);

    return (
        <PublicShell header={menus.header} footer={menus.footer}>
            <Head title={`Checkout — ${hotel.name}`} />

            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <form
                    className="space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((values) => ({
                            ...values,
                            payment_method_id: Number(values.payment_method_id),
                        }));
                        form.post(route('booking.place', hotel.slug));
                    }}
                >
                    <div>
                        <p className="text-sm text-muted-foreground">Secure checkout</p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">Payment method</h1>
                        <p className="mt-2 text-muted-foreground">
                            Cash on delivery places your order now. Card and wallet methods must be completed before the
                            stay is booked.
                        </p>
                    </div>

                    {Object.keys(errors).length > 0 && (
                        <Alert variant="destructive">
                            <CircleAlert />
                            <AlertTitle>Checkout could not continue</AlertTitle>
                            <AlertDescription>{Object.values(errors)[0]}</AlertDescription>
                        </Alert>
                    )}

                    {methods.length === 0 ? (
                        <Alert variant="warning">
                            <CircleAlert />
                            <AlertTitle>Payments are not configured</AlertTitle>
                            <AlertDescription>The hotel has not enabled a payment method yet. Please call reception.</AlertDescription>
                        </Alert>
                    ) : (
                        <ul className="grid gap-3">
                            {methods.map((method) => {
                                const active = form.data.payment_method_id === String(method.id);

                                return (
                                    <li key={method.id}>
                                        <label
                                            className={cn(
                                                'flex cursor-pointer gap-4 rounded-2xl border border-border bg-card p-4 shadow-sm',
                                                active && 'ring-2 ring-ring',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                className="mt-1"
                                                name="payment_method_id"
                                                value={method.id}
                                                checked={active}
                                                onChange={() => form.setData('payment_method_id', String(method.id))}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium">{method.name}</p>
                                                    {method.driver === 'cash_on_delivery' && <Badge variant="secondary">Pay on arrival</Badge>}
                                                    {method.requires_prepaid && <Badge variant="outline">Pay first</Badge>}
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">{method.description}</p>
                                            </div>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {selected && (
                        <p className="rounded-xl bg-muted/60 p-4 text-sm text-muted-foreground">{selected.instructions}</p>
                    )}

                    {!customer && (
                        <div className="space-y-3 rounded-2xl border border-dashed border-border p-4">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="create_account"
                                    checked={form.data.create_account}
                                    onCheckedChange={(checked) => form.setData('create_account', checked === true)}
                                />
                                <div>
                                    <Label htmlFor="create_account">Create a guest account</Label>
                                    <p className="text-sm text-muted-foreground">Track this stay and future bookings in your account.</p>
                                </div>
                            </div>
                            {form.data.create_account && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="password">Password</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={form.data.password}
                                            onChange={(event) => form.setData('password', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="password_confirmation">Confirm password</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={form.data.password_confirmation}
                                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                        />
                                    </div>
                                </div>
                            )}
                            <p className="text-sm text-muted-foreground">
                                Already have an account?{' '}
                                <Link href={route('account.login')} className="underline underline-offset-4">
                                    Sign in
                                </Link>
                            </p>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={form.processing || methods.length === 0}>
                            {selected?.requires_prepaid ? 'Continue to payment' : 'Place order'}
                        </Button>
                        <Button asChild type="button" variant="outline">
                            <Link href={route('booking.show', hotel.slug)}>Back to rooms</Link>
                        </Button>
                    </div>
                </form>

                <aside className="rounded-2xl border border-border bg-card p-5 shadow-sm lg:sticky lg:top-24 lg:self-start">
                    <h2 className="text-lg font-semibold">Stay summary</h2>
                    <dl className="mt-4 space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Room</dt>
                            <dd className="font-medium">{cart.room_type}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Dates</dt>
                            <dd className="font-medium">
                                {cart.check_in_date} → {cart.check_out_date}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Guests</dt>
                            <dd className="font-medium">
                                {cart.adults} adult{cart.adults === 1 ? '' : 's'}
                                {cart.children > 0 ? `, ${cart.children} children` : ''}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Guest</dt>
                            <dd className="font-medium">
                                {cart.first_name} {cart.last_name}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4 border-t border-border pt-3">
                            <dt className="text-muted-foreground">Total</dt>
                            <dd className="text-base font-semibold tabular-nums">{formatMoney(cart.total, cart.currency)}</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </PublicShell>
    );
}
