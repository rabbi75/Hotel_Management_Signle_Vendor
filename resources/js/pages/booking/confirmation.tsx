import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { PublicBookingConfirmation, PublicHotelSummary } from '@/types/booking';
import type { PublicMenuNode } from '@/types/cms';
import { Head, Link } from '@inertiajs/react';
import { Ban, CircleCheck, Clock } from 'lucide-react';
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
    reservation: PublicBookingConfirmation;
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

export default function BookingConfirmation({ hotel, reservation, menus }: Props) {
    const pending = reservation.status === 'pending';
    const confirmed = reservation.status === 'confirmed';
    const inactive = !pending && !confirmed;

    return (
        <PublicShell header={menus.header} footer={menus.footer}>
            <Head title={`${reservation.status_label} · ${reservation.number}`} />

            <div className="mx-auto w-full max-w-xl px-4 py-16 sm:px-6">
                {pending && <Clock className="size-10 text-warning" aria-hidden="true" />}
                {confirmed && <CircleCheck className="size-10 text-success" aria-hidden="true" />}
                {inactive && <Ban className="size-10 text-destructive" aria-hidden="true" />}

                <h1 className="mt-4 text-3xl font-semibold tracking-tight">
                    {pending && 'Request received'}
                    {confirmed && 'Your stay is confirmed'}
                    {inactive && 'This request is no longer active'}
                </h1>
                <p className="mt-3 text-muted-foreground">
                    {pending &&
                        `${hotel.name} has your stay request. It is awaiting hotel confirmation — this is not a guaranteed room yet. We emailed ${reservation.guest_email ?? 'you'} with this reference.`}
                    {confirmed && `${hotel.name} has confirmed this stay. Keep the reference below for check-in.`}
                    {inactive && `${hotel.name} is no longer holding this request. You can send a new one from the booking page.`}
                </p>

                <div className="mt-8 rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">Stay voucher</p>
                        <Badge variant={pending ? 'outline' : confirmed ? 'secondary' : 'destructive'}>{reservation.status_label}</Badge>
                    </div>
                    <dl className="mt-6 space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Reference</dt>
                            <dd className="font-medium tabular-nums">{reservation.number}</dd>
                        </div>
                        {reservation.room_type && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Room type</dt>
                                <dd className="font-medium">{reservation.room_type}</dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Dates</dt>
                            <dd className="font-medium">
                                {reservation.check_in_date} → {reservation.check_out_date}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Guests</dt>
                            <dd className="font-medium">
                                {reservation.adults} adult{reservation.adults === 1 ? '' : 's'}
                                {reservation.children > 0 ? `, ${reservation.children} children` : ''}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Quoted total</dt>
                            <dd className="font-medium tabular-nums">{formatMoney(reservation.total, reservation.currency)}</dd>
                        </div>
                        {reservation.payment_method && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Payment</dt>
                                <dd className="font-medium">
                                    {reservation.payment_method}
                                    {reservation.payment_status_label ? ` · ${reservation.payment_status_label}` : ''}
                                </dd>
                            </div>
                        )}
                        {reservation.payment_reference && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Payment reference</dt>
                                <dd className="font-medium">{reservation.payment_reference}</dd>
                            </div>
                        )}
                        {reservation.guest_email && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Email</dt>
                                <dd className="font-medium">{reservation.guest_email}</dd>
                            </div>
                        )}
                    </dl>
                </div>

                <div className="mt-8 flex flex-wrap gap-3">
                    <Button asChild>
                        <Link href="/">Back to home</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={route('booking.show', hotel.slug)}>Book another stay</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={route('account.bookings')}>View my bookings</Link>
                    </Button>
                </div>
            </div>
        </PublicShell>
    );
}
