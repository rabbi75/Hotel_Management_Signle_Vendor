import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import type { OptionMap, ReportFilters } from '@/types/hotel-reports';
import { router } from '@inertiajs/react';
import { useState } from 'react';

const ALL_HOTELS = '__all__';

interface Props {
    filters: ReportFilters;
    hotels: OptionMap;
    routeName: string;
    showDates?: boolean;
}

export function ReportFiltersBar({ filters, hotels, routeName, showDates = true }: Props) {
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [hotelId, setHotelId] = useState(filters.hotel_id ?? '');

    function apply(): void {
        router.get(
            route(routeName),
            {
                from: from || undefined,
                to: to || undefined,
                hotel_id: hotelId || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <div className="flex flex-wrap items-end gap-4 rounded-lg border bg-card p-4">
            {showDates && (
                <>
                    <div className="space-y-2">
                        <Label htmlFor="from">From</Label>
                        <Input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-[11rem]" />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="to">To</Label>
                        <Input id="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-[11rem]" />
                    </div>
                </>
            )}
            <div className="min-w-[12rem] space-y-2">
                <Label>Hotel</Label>
                <Select value={hotelId || ALL_HOTELS} onValueChange={(v) => setHotelId(v === ALL_HOTELS ? '' : v)}>
                    <SelectTrigger>
                        <SelectValue placeholder="All hotels" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_HOTELS}>All hotels / current context</SelectItem>
                        {Object.entries(hotels).map(([id, name]) => (
                            <SelectItem key={id} value={id}>
                                {name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <Button type="button" onClick={apply}>
                Apply
            </Button>
        </div>
    );
}
