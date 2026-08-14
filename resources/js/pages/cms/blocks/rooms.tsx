import { HotelPhoto } from '@/components/public/hotel-photo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { BlockRepeaterRow } from '@/types/cms';
import { Users } from 'lucide-react';
import { Section, SectionHeading } from '../section';
import { rows, rowStr, str, type BlockRendererProps } from './support';

function formatMoney(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}

function num(row: BlockRepeaterRow, key: string, fallback = 0): number {
    const value = row[key];

    return typeof value === 'number' ? value : fallback;
}

export default function RoomsBlock({ data }: BlockRendererProps) {
    const rooms = rows(data, 'rooms');
    const slug = str(data, 'slug');
    const bookingUrl = str(data, 'booking_url', '/book');
    const checkIn = str(data, 'check_in_date');
    const checkOut = str(data, 'check_out_date');
    const currency = str(data, 'currency', 'USD');
    const buttonLabel = str(data, 'button_label', 'Book this room');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading', 'Rooms')} subheading={str(data, 'subheading')} />

            <form action={slug ? `/book/${slug}` : bookingUrl} method="get" className="mt-10 grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-4">
                <div className="space-y-1.5">
                    <Label htmlFor="landing-check-in">Check-in</Label>
                    <Input id="landing-check-in" name="check_in_date" type="date" defaultValue={checkIn} required />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="landing-check-out">Check-out</Label>
                    <Input id="landing-check-out" name="check_out_date" type="date" defaultValue={checkOut} required />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="landing-adults">Adults</Label>
                    <Input id="landing-adults" name="adults" type="number" min={1} max={20} defaultValue={1} />
                </div>
                <div className="flex items-end">
                    <Button type="submit" className="w-full">
                        Search rooms
                    </Button>
                </div>
            </form>

            {rooms.length === 0 ? (
                <p className="mt-10 text-center text-sm opacity-60">Rooms will appear here once a property is bookable.</p>
            ) : (
                <ul className="mt-10 grid gap-6 sm:grid-cols-2">
                    {rooms.map((room) => {
                        const id = num(room, 'room_type_id');
                        const available = num(room, 'available_rooms');
                        const open = available > 0;
                        const image = rowStr(room, 'image');
                        const href = slug
                            ? `/book/${slug}?check_in_date=${checkIn}&check_out_date=${checkOut}&room_type_id=${id}`
                            : bookingUrl;

                        return (
                            <li key={id || rowStr(room, 'name')} className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                                <div className="aspect-4/3 bg-muted">
                                    <HotelPhoto src={image} alt={rowStr(room, 'name')} seed={id || rowStr(room, 'name')} />
                                </div>
                                <div className="space-y-3 p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 className="text-lg font-semibold">{rowStr(room, 'name')}</h3>
                                            {rowStr(room, 'bed_configuration') && (
                                                <p className="text-sm text-muted-foreground">{rowStr(room, 'bed_configuration')}</p>
                                            )}
                                        </div>
                                        <Badge variant={open ? 'outline' : 'secondary'}>
                                            {open ? `${available} left tonight` : 'Fully booked'}
                                        </Badge>
                                    </div>
                                    {rowStr(room, 'description') && (
                                        <p className="text-sm text-pretty text-muted-foreground">{rowStr(room, 'description')}</p>
                                    )}
                                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Users className="size-4" aria-hidden="true" />
                                        Up to {num(room, 'max_occupancy', 2)} guests
                                    </p>
                                    <div className="flex items-end justify-between gap-3">
                                        <p className="text-xl font-semibold tabular-nums">
                                            {formatMoney(num(room, 'nightly_rate'), rowStr(room, 'currency', currency))}
                                            <span className="text-sm font-normal text-muted-foreground"> / night</span>
                                        </p>
                                        <Button asChild variant={open ? 'default' : 'outline'} disabled={!open} className={cn(!open && 'pointer-events-none opacity-60')}>
                                            <a href={open ? href : undefined}>{open ? buttonLabel : 'Unavailable'}</a>
                                        </Button>
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            <p className="mt-8 text-center">
                <Button asChild variant="ghost">
                    <a href={bookingUrl}>View all rooms</a>
                </Button>
            </p>
        </Section>
    );
}
