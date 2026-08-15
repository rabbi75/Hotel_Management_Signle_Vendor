import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useConfirm } from '@/components/feedback/use-confirm';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { GuestFolioRow } from '@/types/folio';
import type { OptionMap, ReservationRow } from '@/types/reservation';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, LogIn, LogOut, Pencil, Wallet, XCircle } from 'lucide-react';
import { useState } from 'react';

const NONE = '__none__';

interface Props {
    reservation: ReservationRow;
    folio: GuestFolioRow | null;
    can: {
        update: boolean;
        cancel: boolean;
        confirm: boolean;
        check_in: boolean;
        check_out: boolean;
        view_folio: boolean;
        open_folio: boolean;
    };
    rooms: OptionMap;
    beds: OptionMap;
}

export default function ReservationsShow({ reservation, folio, can, rooms, beds }: Props) {
    const confirm = useConfirm();
    const [showCheckIn, setShowCheckIn] = useState(false);
    const checkInForm = useForm({
        room_id: String(reservation.room_id ?? ''),
        bed_id: String(reservation.bed_id ?? ''),
        paid_amount: String(reservation.paid_amount ?? 0),
        notes: '',
    });
    const checkOutForm = useForm({
        paid_amount: String(reservation.total),
        notes: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Reservations', href: route('reservations.index') },
        { label: reservation.number },
    ];

    async function confirmStay(): Promise<void> {
        const ok = await confirm({ title: 'Confirm this reservation?', confirmLabel: 'Confirm stay' });
        if (ok) router.post(route('reservations.confirm', reservation.id), {}, { preserveScroll: true });
    }

    async function cancel(): Promise<void> {
        const ok = await confirm({ title: 'Cancel this reservation?', variant: 'destructive', confirmLabel: 'Cancel reservation' });
        if (ok) router.post(route('reservations.cancel', reservation.id), {}, { preserveScroll: true });
    }

    function submitCheckIn(event: React.FormEvent): void {
        event.preventDefault();
        checkInForm.transform((v) => ({
            ...v,
            room_id: v.room_id === '' ? null : v.room_id,
            bed_id: v.bed_id === '' ? null : v.bed_id,
            paid_amount: Number(v.paid_amount),
            notes: v.notes || null,
        }));
        checkInForm.post(route('reservations.check-in', reservation.id), {
            preserveScroll: true,
            onSuccess: () => setShowCheckIn(false),
        });
    }

    function submitCheckOut(event: React.FormEvent): void {
        event.preventDefault();
        checkOutForm.transform((v) => ({
            paid_amount: Number(v.paid_amount),
            notes: v.notes || null,
        }));
        checkOutForm.post(route('reservations.check-out', reservation.id), { preserveScroll: true });
    }

    return (
        <AppLayout title={reservation.number} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={reservation.number}
                    description={`${reservation.guest ?? 'Guest'} · ${reservation.check_in_date} → ${reservation.check_out_date}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={route('reservations.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back
                                </Link>
                            </Button>
                            {can.update && reservation.can_cancel && (
                                <Button asChild variant="outline">
                                    <Link href={route('reservations.edit', reservation.id)}>
                                        <Pencil className="size-4" aria-hidden="true" />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                            {can.update && reservation.can_confirm && (
                                <Button onClick={() => void confirmStay()}>
                                    <CheckCircle2 className="size-4" aria-hidden="true" />
                                    Confirm
                                </Button>
                            )}
                            {can.check_in && reservation.can_check_in && (
                                <Button onClick={() => setShowCheckIn((v) => !v)}>
                                    <LogIn className="size-4" aria-hidden="true" />
                                    Check in
                                </Button>
                            )}
                            {can.cancel && reservation.can_cancel && (
                                <Button variant="destructive" onClick={() => void cancel()}>
                                    <XCircle className="size-4" aria-hidden="true" />
                                    Cancel
                                </Button>
                            )}
                        </div>
                    }
                />

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3">
                        <CardTitle>Overview</CardTitle>
                        <Badge variant="outline">{reservation.status_label}</Badge>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground">Hotel</p>
                            <p>{reservation.hotel || '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Room / Bed</p>
                            <p>{reservation.room || reservation.bed || 'Unassigned'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Nights</p>
                            <p>{reservation.nights}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Source</p>
                            <p>{reservation.booking_source_label}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Total</p>
                            <p className="tabular-nums">{reservation.total}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Paid / Due</p>
                            <p className="tabular-nums">
                                {reservation.paid_amount} / {reservation.due_amount}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Payment method</p>
                            <p>{reservation.payment_method || '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Payment status</p>
                            <p>{reservation.payment_status_label || '—'}</p>
                        </div>
                        {reservation.payment_reference && (
                            <div className="sm:col-span-2">
                                <p className="text-muted-foreground">Payment reference</p>
                                <p>{reservation.payment_reference}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                        <CardTitle>Guest requests & notes</CardTitle>
                        <div className="flex flex-wrap gap-2">
                            <AiAssistButton
                                action="reservation.staff_brief"
                                label="Staff brief"
                                subjectId={reservation.id}
                                description="Three-line briefing for the front desk from special requests and notes."
                            />
                            <AiAssistButton
                                action="reservation.draft_confirmation"
                                label="Draft confirmation"
                                subjectId={reservation.id}
                                description="Guest-facing booking confirmation message."
                            />
                            <AiAssistButton
                                action="reservation.draft_pre_arrival"
                                label="Pre-arrival"
                                subjectId={reservation.id}
                                description="Warm pre-arrival reminder for the guest."
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <p className="text-muted-foreground">Special requests</p>
                            <p className="whitespace-pre-wrap">{reservation.special_requests || '—'}</p>
                        </div>
                        <div className="sm:col-span-2">
                            <p className="text-muted-foreground">Internal notes</p>
                            <p className="whitespace-pre-wrap">{reservation.notes || '—'}</p>
                        </div>
                    </CardContent>
                </Card>

                {can.view_folio && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-3">
                            <CardTitle>Guest folio</CardTitle>
                            {folio && <Badge variant="outline">{folio.status_label}</Badge>}
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {folio ? (
                                <>
                                    <p>
                                        Folio <span className="font-medium">{folio.number}</span> — balance{' '}
                                        <span className="tabular-nums">{folio.balance}</span> (minor units)
                                    </p>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={route('folios.show', folio.id)}>
                                            <Wallet className="size-4" aria-hidden="true" />
                                            View folio
                                        </Link>
                                    </Button>
                                </>
                            ) : can.open_folio ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.post(route('folios.open-from-reservation', reservation.id))}
                                >
                                    <Wallet className="size-4" aria-hidden="true" />
                                    Open folio
                                </Button>
                            ) : (
                                <p className="text-muted-foreground">Folio opens automatically on check-in.</p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {showCheckIn && can.check_in && reservation.can_check_in && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Check-in</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitCheckIn} className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Room</Label>
                                    <Select
                                        value={checkInForm.data.room_id || NONE}
                                        onValueChange={(v) => checkInForm.setData('room_id', v === NONE ? '' : v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Assign room" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>None</SelectItem>
                                            {Object.entries(rooms).map(([id, name]) => (
                                                <SelectItem key={id} value={id}>
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Bed</Label>
                                    <Select
                                        value={checkInForm.data.bed_id || NONE}
                                        onValueChange={(v) => checkInForm.setData('bed_id', v === NONE ? '' : v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Optional bed" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>None</SelectItem>
                                            {Object.entries(beds).map(([id, name]) => (
                                                <SelectItem key={id} value={id}>
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Paid amount</Label>
                                    <Input
                                        type="number"
                                        value={checkInForm.data.paid_amount}
                                        onChange={(e) => checkInForm.setData('paid_amount', e.target.value)}
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <Button type="submit" disabled={checkInForm.processing}>
                                        Confirm check-in
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {can.check_out && reservation.can_check_out && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Check-out</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitCheckOut} className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Final paid amount</Label>
                                    <Input
                                        type="number"
                                        value={checkOutForm.data.paid_amount}
                                        onChange={(e) => checkOutForm.setData('paid_amount', e.target.value)}
                                    />
                                </div>
                                <div className="flex items-end">
                                    <Button type="submit" disabled={checkOutForm.processing}>
                                        <LogOut className="size-4" aria-hidden="true" />
                                        Complete check-out
                                    </Button>
                                </div>
                            </form>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Closes the guest folio and generates a printable invoice. Room status becomes Dirty after check-out.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
