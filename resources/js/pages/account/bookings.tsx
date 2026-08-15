import { AccountShell } from '@/components/public/account-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { PublicMenuNode } from '@/types/cms';
import { Head, Link } from '@inertiajs/react';

interface BookingRow {
    number: string;
    room_type: string | null;
    check_in_date: string;
    check_out_date: string;
    status_label: string;
    payment_status_label: string | null;
    total: number;
    currency: string;
}

interface Props {
    customer: { name: string };
    bookings: BookingRow[];
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

function formatMoney(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}

export default function AccountBookings({ customer, bookings, menus }: Props) {
    return (
        <AccountShell title="My bookings" description="Every stay you have requested or paid for." customerName={customer.name} menus={menus}>
            <Head title="My bookings" />

            {bookings.length === 0 ? (
                <p className="text-sm text-muted-foreground">You have not placed a stay order yet.</p>
            ) : (
                <ul className="grid gap-4">
                    {bookings.map((booking) => (
                        <li key={booking.number} className="rounded-2xl border border-border bg-card p-5 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium">{booking.number}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {booking.room_type} · {booking.check_in_date} → {booking.check_out_date}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">{booking.status_label}</Badge>
                                    {booking.payment_status_label && <Badge variant="secondary">{booking.payment_status_label}</Badge>}
                                </div>
                            </div>
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm font-medium tabular-nums">{formatMoney(booking.total, booking.currency)}</p>
                                <Button asChild size="sm">
                                    <Link href={route('account.bookings.show', booking.number)}>View order</Link>
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </AccountShell>
    );
}
