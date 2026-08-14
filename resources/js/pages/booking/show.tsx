import { HotelPhoto } from '@/components/public/hotel-photo';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { PublicBookingFilters, PublicCatalogRoom, PublicHotelSummary } from '@/types/booking';
import type { SharedProps } from '@/types';
import type { PublicMenuNode } from '@/types/cms';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CircleAlert, MapPin, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
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
    rooms: PublicCatalogRoom[];
    filters: PublicBookingFilters;
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

export default function BookingShow({ hotel, rooms, filters, menus }: Props) {
    const { errors } = usePage<SharedProps>().props;
    const [checkIn, setCheckIn] = useState(filters.check_in_date ?? '');
    const [checkOut, setCheckOut] = useState(filters.check_out_date ?? '');
    const [adults, setAdults] = useState(String(filters.adults || 1));
    const [children, setChildren] = useState(String(filters.children || 0));

    const form = useForm({
        room_type_id: filters.room_type_id ? String(filters.room_type_id) : '',
        check_in_date: filters.check_in_date ?? '',
        check_out_date: filters.check_out_date ?? '',
        adults: String(filters.adults || 1),
        children: String(filters.children || 0),
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        special_requests: '',
    });

    const datesReady = checkIn !== '' && checkOut !== '' && checkOut > checkIn;
    const party = Number(adults) + Number(children);
    const selected = rooms.find((room) => String(room.room_type_id) === form.data.room_type_id);

    const selectable = useMemo(() => {
        return rooms.map((room) => {
            const fits = party <= (room.max_occupancy || 99);
            const open = room.available_rooms > 0 && fits;

            return { room, open, reason: !fits ? 'Too small for this party' : room.available_rooms < 1 ? 'Unavailable for these dates' : null };
        });
    }, [rooms, party]);

    function checkAvailability(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        router.get(
            route('booking.show', hotel.slug),
            {
                check_in_date: checkIn,
                check_out_date: checkOut,
                adults,
                children,
                room_type_id: form.data.room_type_id || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );

        form.setData({
            ...form.data,
            check_in_date: checkIn,
            check_out_date: checkOut,
            adults,
            children,
        });
    }

    function selectRoom(id: number): void {
        form.setData({
            ...form.data,
            room_type_id: String(id),
            check_in_date: checkIn,
            check_out_date: checkOut,
            adults,
            children,
        });
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            adults: Number(values.adults),
            children: Number(values.children),
            room_type_id: Number(values.room_type_id),
        }));
        form.post(route('booking.store', hotel.slug));
    }

    return (
        <PublicShell header={menus.header} footer={menus.footer}>
            <Head title={`Book a room — ${hotel.name}`} />

            <section className="relative isolate overflow-hidden border-b border-border">
                <HotelPhoto src={hotel.cover} seed="hero" decorative className="absolute inset-0 -z-10 opacity-20" />
                <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
                    <p className="flex items-center gap-2 text-sm font-medium tracking-wide text-muted-foreground uppercase">
                        <MapPin className="size-4" aria-hidden="true" />
                        {[hotel.city, hotel.country].filter(Boolean).join(', ') || 'City stay'}
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight sm:text-5xl">Stay at {hotel.name}</h1>
                    {hotel.description && <p className="mt-4 max-w-2xl text-muted-foreground">{hotel.description}</p>}
                    <p className="mt-3 text-sm text-muted-foreground">
                        Check-in from {hotel.check_in_time}. Check-out by {hotel.check_out_time}. Requests are held until the
                        front desk confirms. No payment is taken online.
                    </p>
                </div>
            </section>

            <div className="sticky top-[3.25rem] z-20 border-b border-border/80 bg-background/90 backdrop-blur-md">
                <form onSubmit={checkAvailability} className="mx-auto grid w-full max-w-6xl gap-3 px-4 py-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="check_in_date">Check-in</Label>
                        <Input
                            id="check_in_date"
                            type="date"
                            required
                            min={new Date().toISOString().slice(0, 10)}
                            value={checkIn}
                            onChange={(event) => setCheckIn(event.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="check_out_date">Check-out</Label>
                        <Input
                            id="check_out_date"
                            type="date"
                            required
                            min={checkIn || new Date().toISOString().slice(0, 10)}
                            value={checkOut}
                            onChange={(event) => setCheckOut(event.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="adults">Adults</Label>
                        <Input id="adults" type="number" min={1} max={20} value={adults} onChange={(event) => setAdults(event.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="children">Children</Label>
                        <Input id="children" type="number" min={0} max={20} value={children} onChange={(event) => setChildren(event.target.value)} />
                    </div>
                    <div className="flex items-end">
                        <Button type="submit" className="w-full" disabled={!datesReady}>
                            Search rooms
                        </Button>
                    </div>
                </form>
            </div>

            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="space-y-6">
                    {selectable.every((item) => !item.open) && (
                        <Alert variant="warning">
                            <CircleAlert />
                            <AlertTitle>No rooms for those dates</AlertTitle>
                            <AlertDescription>Try different dates, or call reception if you need a specific room type.</AlertDescription>
                        </Alert>
                    )}

                    <ul className="grid gap-6">
                        {selectable.map(({ room, open, reason }) => {
                            const isSelected = form.data.room_type_id === String(room.room_type_id);

                            return (
                                <li
                                    key={room.room_type_id}
                                    className={cn(
                                        'overflow-hidden rounded-2xl border border-border bg-card shadow-sm',
                                        isSelected && 'ring-2 ring-ring',
                                        !open && 'opacity-80',
                                    )}
                                >
                                    <div className="grid sm:grid-cols-[16rem_minmax(0,1fr)]">
                                        <div className="aspect-4/3 bg-muted sm:aspect-auto sm:min-h-full">
                                            <HotelPhoto src={room.image} alt={room.name} seed={room.room_type_id} />
                                        </div>
                                        <div className="flex flex-col gap-3 p-5">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <h2 className="text-xl font-semibold">{room.name}</h2>
                                                    {room.bed_configuration && (
                                                        <p className="text-sm text-muted-foreground">{room.bed_configuration}</p>
                                                    )}
                                                </div>
                                                <Badge variant={open ? 'outline' : 'secondary'}>
                                                    {open ? `${room.available_rooms} left` : reason}
                                                </Badge>
                                            </div>
                                            {room.description && <p className="text-sm text-muted-foreground">{room.description}</p>}
                                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Users className="size-4" aria-hidden="true" />
                                                Up to {room.max_occupancy} guests
                                            </p>
                                            {room.facilities.length > 0 && (
                                                <p className="text-xs text-muted-foreground">{room.facilities.join(' · ')}</p>
                                            )}
                                            <div className="mt-auto flex flex-wrap items-end justify-between gap-3">
                                                <div>
                                                    <p className="text-xl font-semibold tabular-nums">
                                                        {formatMoney(room.nightly_rate, room.currency)}
                                                        <span className="text-sm font-normal text-muted-foreground"> / night</span>
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {room.nights} night{room.nights === 1 ? '' : 's'} · {formatMoney(room.subtotal, room.currency)}
                                                    </p>
                                                </div>
                                                <Button type="button" variant={isSelected ? 'default' : 'outline'} disabled={!open} onClick={() => selectRoom(room.room_type_id)}>
                                                    {isSelected ? 'Selected' : open ? 'Select this room' : 'Unavailable'}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>

                <aside className="lg:sticky lg:top-36 lg:self-start">
                    {selected ? (
                        <form onSubmit={submit} className="space-y-5 rounded-2xl border border-border bg-card p-5 shadow-sm">
                            <div>
                                <h2 className="text-lg font-semibold">Request this stay</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {selected.name} · {form.data.check_in_date} → {form.data.check_out_date}
                                </p>
                                <p className="mt-2 text-sm font-medium tabular-nums">{formatMoney(selected.subtotal, selected.currency)}</p>
                            </div>

                            {Object.keys(errors).length > 0 && (
                                <Alert variant="destructive">
                                    <CircleAlert />
                                    <AlertTitle>We could not submit this request</AlertTitle>
                                    <AlertDescription>{Object.values(errors)[0]}</AlertDescription>
                                </Alert>
                            )}

                            <div className="grid gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="first_name">First name</Label>
                                    <Input
                                        id="first_name"
                                        required
                                        value={form.data.first_name}
                                        onChange={(event) => form.setData('first_name', event.target.value)}
                                        aria-invalid={Boolean(errors.first_name)}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="last_name">Last name</Label>
                                    <Input
                                        id="last_name"
                                        required
                                        value={form.data.last_name}
                                        onChange={(event) => form.setData('last_name', event.target.value)}
                                        aria-invalid={Boolean(errors.last_name)}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        value={form.data.email}
                                        onChange={(event) => form.setData('email', event.target.value)}
                                        aria-invalid={Boolean(errors.email)}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input id="phone" type="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="special_requests">Special requests</Label>
                                    <Textarea
                                        id="special_requests"
                                        rows={3}
                                        value={form.data.special_requests}
                                        onChange={(event) => form.setData('special_requests', event.target.value)}
                                    />
                                </div>
                            </div>

                            <p className="text-xs text-muted-foreground">No payment online. The hotel will confirm by email.</p>
                            <Button type="submit" className="w-full" disabled={form.processing || !datesReady}>
                                Request this stay
                            </Button>
                        </form>
                    ) : (
                        <div className="rounded-2xl border border-dashed border-border p-5 text-sm text-muted-foreground">
                            Select a room to send a stay request. Availability is for the dates in the search bar.
                        </div>
                    )}
                </aside>
            </div>
        </PublicShell>
    );
}
