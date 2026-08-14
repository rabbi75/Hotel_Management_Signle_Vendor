import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { CalendarReservation, CalendarRoom, OptionMap } from '@/types/reservation';
import { Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

const NONE = '__none__';

interface Props {
    start: string;
    end: string;
    days: string[];
    hotelId: number | null;
    filters: { hotel_id: number | null; floor_id: string | number | null; room_type_id: string | number | null; status: string | null };
    hotels: OptionMap;
    floors: OptionMap;
    roomTypes: OptionMap;
    rooms: CalendarRoom[];
    reservations: CalendarReservation[];
    canCreate: boolean;
}

export default function AvailabilityCalendar({ start, end, days, filters, hotels, floors, roomTypes, rooms, reservations, canCreate }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Reservations', href: route('reservations.index') },
        { label: 'Calendar' },
    ];

    const [hotelId, setHotelId] = useState(String(filters.hotel_id ?? ''));
    const [floorId, setFloorId] = useState(String(filters.floor_id ?? ''));
    const [roomTypeId, setRoomTypeId] = useState(String(filters.room_type_id ?? ''));
    const [rangeStart, setRangeStart] = useState(start);
    const [rangeEnd, setRangeEnd] = useState(end);

    const byRoom = useMemo(() => {
        const map = new Map<number, CalendarReservation[]>();
        for (const reservation of reservations) {
            if (!reservation.room_id) continue;
            const list = map.get(reservation.room_id) ?? [];
            list.push(reservation);
            map.set(reservation.room_id, list);
        }
        return map;
    }, [reservations]);

    function applyFilters(): void {
        router.get(
            route('reservations.calendar'),
            {
                start: rangeStart,
                end: rangeEnd,
                hotel_id: hotelId || undefined,
                floor_id: floorId || undefined,
                room_type_id: roomTypeId || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    function cellFor(roomId: number, day: string): CalendarReservation | null {
        const list = byRoom.get(roomId) ?? [];
        return (
            list.find((r) => r.check_in_date <= day && r.check_out_date > day) ?? null
        );
    }

    return (
        <AppLayout title="Availability calendar" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Availability calendar"
                    description="Room occupancy across the selected dates."
                    actions={
                        canCreate ? (
                            <Button asChild>
                                <Link href={route('reservations.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New reservation
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-lg border p-4">
                    <div className="space-y-1">
                        <p className="text-xs text-muted-foreground">From</p>
                        <Input type="date" value={rangeStart} onChange={(e) => setRangeStart(e.target.value)} />
                    </div>
                    <div className="space-y-1">
                        <p className="text-xs text-muted-foreground">To</p>
                        <Input type="date" value={rangeEnd} onChange={(e) => setRangeEnd(e.target.value)} />
                    </div>
                    <div className="space-y-1">
                        <p className="text-xs text-muted-foreground">Hotel</p>
                        <Select value={hotelId || NONE} onValueChange={(v) => setHotelId(v === NONE ? '' : v)}>
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>All hotels</SelectItem>
                                {Object.entries(hotels).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1">
                        <p className="text-xs text-muted-foreground">Floor</p>
                        <Select value={floorId || NONE} onValueChange={(v) => setFloorId(v === NONE ? '' : v)}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>All floors</SelectItem>
                                {Object.entries(floors).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1">
                        <p className="text-xs text-muted-foreground">Room type</p>
                        <Select value={roomTypeId || NONE} onValueChange={(v) => setRoomTypeId(v === NONE ? '' : v)}>
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>All types</SelectItem>
                                {Object.entries(roomTypes).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button type="button" onClick={applyFilters}>
                        Apply
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="min-w-full border-collapse text-sm">
                        <thead>
                            <tr className="bg-muted/40">
                                <th className="sticky left-0 z-10 min-w-36 border-b bg-muted/40 px-3 py-2 text-left font-medium">Room</th>
                                {days.map((day) => (
                                    <th key={day} className="min-w-24 border-b px-2 py-2 text-center font-medium tabular-nums">
                                        {day.slice(5)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rooms.length === 0 ? (
                                <tr>
                                    <td colSpan={days.length + 1} className="px-3 py-8 text-center text-muted-foreground">
                                        No rooms match these filters.
                                    </td>
                                </tr>
                            ) : (
                                rooms.map((room) => (
                                    <tr key={room.id} className="border-b last:border-0">
                                        <td className="sticky left-0 z-10 bg-background px-3 py-2">
                                            <div className="font-medium">{room.number}</div>
                                            <div className="text-xs text-muted-foreground">{room.room_type || room.status_label}</div>
                                        </td>
                                        {days.map((day) => {
                                            const reservation = cellFor(room.id, day);
                                            return (
                                                <td key={`${room.id}-${day}`} className="px-1 py-1">
                                                    {reservation ? (
                                                        <Link
                                                            href={route('reservations.show', reservation.id)}
                                                            className={cn(
                                                                'block truncate rounded px-1.5 py-1 text-xs font-medium',
                                                                reservation.status === 'checked_in' && 'bg-amber-100 text-amber-900',
                                                                reservation.status === 'confirmed' && 'bg-sky-100 text-sky-900',
                                                                reservation.status === 'pending' && 'bg-zinc-100 text-zinc-800',
                                                            )}
                                                            title={`${reservation.guest ?? reservation.number}`}
                                                        >
                                                            {reservation.guest ?? reservation.number}
                                                        </Link>
                                                    ) : (
                                                        <div className="h-7 rounded bg-emerald-50/80" title="Available" />
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-3 rounded bg-emerald-50 ring-1 ring-emerald-200" /> Available
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <Badge variant="outline">Pending / Confirmed / Checked in</Badge>
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}
