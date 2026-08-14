import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { EnumOption, OptionMap, ReservationRow } from '@/types/reservation';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    reservation?: ReservationRow;
    hotels: OptionMap;
    guests: OptionMap;
    rooms: OptionMap;
    beds: OptionMap;
    roomTypes: OptionMap;
    statuses: EnumOption[];
    sources: EnumOption[];
    defaultHotelId?: number | null;
}

export function ReservationForm({
    reservation,
    hotels,
    guests,
    rooms,
    beds,
    roomTypes,
    statuses,
    sources,
    defaultHotelId,
}: Props) {
    const editing = reservation !== undefined;
    const form = useForm({
        hotel_id: String(reservation?.hotel_id ?? defaultHotelId ?? ''),
        guest_id: String(reservation?.guest_id ?? ''),
        room_id: String(reservation?.room_id ?? ''),
        bed_id: String(reservation?.bed_id ?? ''),
        room_type_id: String(reservation?.room_type_id ?? ''),
        check_in_date: reservation?.check_in_date ?? '',
        check_out_date: reservation?.check_out_date ?? '',
        adults: String(reservation?.adults ?? 1),
        children: String(reservation?.children ?? 0),
        rooms_count: String(reservation?.rooms_count ?? 1),
        booking_source: reservation?.booking_source ?? 'walk_in',
        special_requests: reservation?.special_requests ?? '',
        notes: reservation?.notes ?? '',
        discount: String(reservation?.discount ?? 0),
        tax: String(reservation?.tax ?? 0),
        total: String(reservation?.total ?? 0),
        paid_amount: String(reservation?.paid_amount ?? 0),
        status: reservation?.status ?? 'pending',
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : values.hotel_id,
            guest_id: values.guest_id === '' ? null : values.guest_id,
            room_id: values.room_id === '' ? null : values.room_id,
            bed_id: values.bed_id === '' ? null : values.bed_id,
            room_type_id: values.room_type_id === '' ? null : values.room_type_id,
            adults: Number(values.adults),
            children: Number(values.children),
            rooms_count: Number(values.rooms_count),
            discount: Number(values.discount),
            tax: Number(values.tax),
            total: Number(values.total),
            paid_amount: Number(values.paid_amount),
        }));
        if (editing && reservation) {
            form.put(route('reservations.update', reservation.id), { preserveScroll: true });
            return;
        }
        form.post(route('reservations.store'));
    }

    function select(label: string, key: keyof typeof data, options: OptionMap, required = false) {
        return (
            <div className="space-y-2">
                <Label>
                    {label}
                    {required ? ' *' : ''}
                </Label>
                <Select value={String(data[key] || NONE)} onValueChange={(v) => setData(key, v === NONE ? '' : v)}>
                    <SelectTrigger>
                        <SelectValue placeholder={required ? 'Select' : 'Optional'} />
                    </SelectTrigger>
                    <SelectContent>
                        {!required && <SelectItem value={NONE}>None</SelectItem>}
                        {Object.entries(options).map(([id, name]) => (
                            <SelectItem key={id} value={id}>
                                {name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors[key] && <p className="text-sm text-destructive">{errors[key]}</p>}
            </div>
        );
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>Could not save</AlertTitle>
                    <AlertDescription>Check the highlighted fields. Overlapping dates are blocked server-side.</AlertDescription>
                </Alert>
            )}
            <Card>
                <CardHeader>
                    <CardTitle>Reservation</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    {select('Hotel', 'hotel_id', hotels, true)}
                    {select('Guest', 'guest_id', guests, true)}
                    {select('Room', 'room_id', rooms)}
                    {select('Bed', 'bed_id', beds)}
                    {select('Room type', 'room_type_id', roomTypes)}
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Source</Label>
                        <Select value={data.booking_source} onValueChange={(v) => setData('booking_source', v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {sources.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="check_in_date">Check-in *</Label>
                        <Input id="check_in_date" type="date" value={data.check_in_date} onChange={(e) => setData('check_in_date', e.target.value)} />
                        {errors.check_in_date && <p className="text-sm text-destructive">{errors.check_in_date}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="check_out_date">Check-out *</Label>
                        <Input id="check_out_date" type="date" value={data.check_out_date} onChange={(e) => setData('check_out_date', e.target.value)} />
                        {errors.check_out_date && <p className="text-sm text-destructive">{errors.check_out_date}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="adults">Adults</Label>
                        <Input id="adults" type="number" value={data.adults} onChange={(e) => setData('adults', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="children">Children</Label>
                        <Input id="children" type="number" value={data.children} onChange={(e) => setData('children', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="total">Total (minor units)</Label>
                        <Input id="total" type="number" value={data.total} onChange={(e) => setData('total', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="paid_amount">Paid amount</Label>
                        <Input id="paid_amount" type="number" value={data.paid_amount} onChange={(e) => setData('paid_amount', e.target.value)} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label htmlFor="special_requests">Special requests</Label>
                            <AiAssistButton
                                action="reservation.staff_brief"
                                label="Summarise"
                                subjectId={reservation?.id}
                                draft={{
                                    special_requests: data.special_requests,
                                    notes: data.notes,
                                    check_in_date: data.check_in_date,
                                    check_out_date: data.check_out_date,
                                    guest_name: guests[data.guest_id],
                                    hotel_name: hotels[data.hotel_id],
                                    room_type_name: roomTypes[data.room_type_id],
                                    adults: data.adults,
                                    children: data.children,
                                }}
                                onApply={(text) => setData('special_requests', text)}
                                applyLabel="Replace special requests"
                                description="Summarise or rewrite special requests for staff clarity."
                            />
                        </div>
                        <Textarea id="special_requests" rows={2} value={data.special_requests} onChange={(e) => setData('special_requests', e.target.value)} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <AiAssistButton
                                action="reservation.draft_confirmation"
                                label="Draft confirmation"
                                subjectId={reservation?.id}
                                draft={{
                                    special_requests: data.special_requests,
                                    notes: data.notes,
                                    check_in_date: data.check_in_date,
                                    check_out_date: data.check_out_date,
                                    guest_name: guests[data.guest_id],
                                    hotel_name: hotels[data.hotel_id],
                                    room_type_name: roomTypes[data.room_type_id],
                                }}
                                onApply={(text) => setData('notes', text)}
                                applyLabel="Save draft into notes"
                            />
                        </div>
                        <Textarea id="notes" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                </CardContent>
            </Card>
            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save reservation' : 'Create reservation'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
