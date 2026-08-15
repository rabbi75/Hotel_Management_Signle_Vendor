import { AccountShell } from '@/components/public/account-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { PublicMenuNode } from '@/types/cms';
import { Head, Link } from '@inertiajs/react';

interface BookingRow {
    number: string;
    hotel: string | null;
    room_type: string | null;
    check_in_date: string;
    check_out_date: string;
    status_label: string;
    total: number;
    currency: string;
}

interface Props {
    customer: { name: string };
    stats: { bookings: number; upcoming: number };
    recent: BookingRow[];
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

export default function AccountDashboard({ customer, stats, recent, menus }: Props) {
    return (
        <AccountShell title="Your stays" description="Manage bookings like an order history." customerName={customer.name} menus={menus}>
            <Head title="My account" />

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="rounded-2xl border border-border bg-card p-5">
                    <p className="text-sm text-muted-foreground">All bookings</p>
                    <p className="mt-2 text-3xl font-semibold tabular-nums">{stats.bookings}</p>
                </div>
                <div className="rounded-2xl border border-border bg-card p-5">
                    <p className="text-sm text-muted-foreground">Upcoming</p>
                    <p className="mt-2 text-3xl font-semibold tabular-nums">{stats.upcoming}</p>
                </div>
            </div>

            <div className="mt-8 flex items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">Recent orders</h2>
                <Button asChild variant="outline" size="sm">
                    <Link href={route('account.bookings')}>View all</Link>
                </Button>
            </div>

            {recent.length === 0 ? (
                <p className="mt-4 text-sm text-muted-foreground">No bookings yet. Reserve a room from the booking page.</p>
            ) : (
                <ul className="mt-4 divide-y divide-border rounded-2xl border border-border bg-card">
                    {recent.map((booking) => (
                        <li key={booking.number} className="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div>
                                <p className="font-medium">{booking.number}</p>
                                <p className="text-sm text-muted-foreground">
                                    {booking.room_type} · {booking.check_in_date} → {booking.check_out_date}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <Badge variant="outline">{booking.status_label}</Badge>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={route('account.bookings.show', booking.number)}>Details</Link>
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </AccountShell>
    );
}
