import { AccountShell } from '@/components/public/account-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { PublicMenuNode } from '@/types/cms';
import { Head, Link } from '@inertiajs/react';

interface BookingDetail {
    number: string;
    hotel: string | null;
    room_type: string | null;
    check_in_date: string;
    check_out_date: string;
    adults: number;
    children: number;
    total: number;
    paid_amount: number;
    due_amount: number;
    currency: string;
    status_label: string;
    payment_method: string | null;
    payment_status_label: string | null;
    payment_reference: string | null;
    special_requests: string | null;
}

interface Props {
    customer: { name: string };
    booking: BookingDetail;
    confirmation_url: string | null;
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

function formatMoney(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}

export default function AccountBookingShow({ customer, booking, confirmation_url, menus }: Props) {
    return (
        <AccountShell title={booking.number} description="Order details for this stay." customerName={customer.name} menus={menus}>
            <Head title={booking.number} />

            <div className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">{booking.hotel}</p>
                    <Badge variant="outline">{booking.status_label}</Badge>
                </div>
                <dl className="mt-6 space-y-3 text-sm">
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">Room</dt>
                        <dd className="font-medium">{booking.room_type}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">Dates</dt>
                        <dd className="font-medium">
                            {booking.check_in_date} → {booking.check_out_date}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">Guests</dt>
                        <dd className="font-medium">
                            {booking.adults} adult{booking.adults === 1 ? '' : 's'}
                            {booking.children > 0 ? `, ${booking.children} children` : ''}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">Total / paid / due</dt>
                        <dd className="font-medium tabular-nums">
                            {formatMoney(booking.total, booking.currency)} / {formatMoney(booking.paid_amount, booking.currency)} /{' '}
                            {formatMoney(booking.due_amount, booking.currency)}
                        </dd>
                    </div>
                    {booking.payment_method && (
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Payment</dt>
                            <dd className="font-medium">
                                {booking.payment_method}
                                {booking.payment_status_label ? ` · ${booking.payment_status_label}` : ''}
                            </dd>
                        </div>
                    )}
                    {booking.payment_reference && (
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Reference</dt>
                            <dd className="font-medium">{booking.payment_reference}</dd>
                        </div>
                    )}
                    {booking.special_requests && (
                        <div>
                            <dt className="text-muted-foreground">Requests</dt>
                            <dd className="mt-1 whitespace-pre-wrap">{booking.special_requests}</dd>
                        </div>
                    )}
                </dl>
            </div>

            <div className="mt-6 flex flex-wrap gap-3">
                {confirmation_url && (
                    <Button asChild>
                        <Link href={confirmation_url}>Open voucher</Link>
                    </Button>
                )}
                <Button asChild variant="outline">
                    <Link href={route('account.bookings')}>Back to bookings</Link>
                </Button>
            </div>
        </AccountShell>
    );
}
