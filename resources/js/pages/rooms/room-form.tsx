import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { EnumOption, OptionMap, RoomRow } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    room?: RoomRow;
    hotels: OptionMap;
    buildings: OptionMap;
    floors: OptionMap;
    roomTypes: OptionMap;
    facilities: OptionMap;
    statuses: EnumOption[];
    defaultHotelId?: number | null;
}

export function RoomForm({ room, hotels, buildings, floors, roomTypes, facilities, statuses, defaultHotelId }: Props) {
    const editing = room !== undefined;
    const form = useForm({
        hotel_id: String(room?.hotel_id ?? defaultHotelId ?? ''),
        building_id: String(room?.building_id ?? ''),
        floor_id: String(room?.floor_id ?? ''),
        room_type_id: String(room?.room_type_id ?? ''),
        number: room?.number ?? '',
        code: room?.code ?? '',
        description: room?.description ?? '',
        base_price: room?.base_price != null ? String(room.base_price) : '',
        max_occupancy: room?.max_occupancy != null ? String(room.max_occupancy) : '',
        status: room?.status ?? 'available',
        is_active: room?.is_active ?? true,
        facility_ids: (room?.facility_ids ?? []).map(String),
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : values.hotel_id,
            building_id: values.building_id === '' ? null : values.building_id,
            floor_id: values.floor_id === '' ? null : values.floor_id,
            room_type_id: values.room_type_id === '' ? null : values.room_type_id,
            base_price: values.base_price === '' ? null : Number(values.base_price),
            max_occupancy: values.max_occupancy === '' ? null : Number(values.max_occupancy),
            facility_ids: values.facility_ids.map(Number),
        }));
        if (editing && room) {
            form.put(route('rooms.update', room.id), { preserveScroll: true });
            return;
        }
        form.post(route('rooms.store'));
    }

    function selectField(label: string, key: keyof typeof data, options: OptionMap, optional = true) {
        const value = String(data[key] ?? '');
        return (
            <div className="space-y-2">
                <Label>{label}</Label>
                <Select value={value || NONE} onValueChange={(v) => setData(key, v === NONE ? '' : v)}>
                    <SelectTrigger>
                        <SelectValue placeholder={optional ? 'Optional' : 'Select'} />
                    </SelectTrigger>
                    <SelectContent>
                        {optional && <SelectItem value={NONE}>None</SelectItem>}
                        {Object.entries(options).map(([id, name]) => (
                            <SelectItem key={id} value={id}>
                                {name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        );
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>Could not save</AlertTitle>
                    <AlertDescription>Check the highlighted fields.</AlertDescription>
                </Alert>
            )}
            <Card>
                <CardHeader>
                    <CardTitle>Room</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2 sm:col-span-2">
                        <Label>Hotel *</Label>
                        <Select value={data.hotel_id || NONE} onValueChange={(v) => setData('hotel_id', v === NONE ? '' : v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select hotel" />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(hotels).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.hotel_id && <p className="text-sm text-destructive">{errors.hotel_id}</p>}
                    </div>
                    {selectField('Building', 'building_id', buildings)}
                    {selectField('Floor', 'floor_id', floors)}
                    {selectField('Room type', 'room_type_id', roomTypes)}
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
                        <Label htmlFor="number">Room number *</Label>
                        <Input id="number" value={data.number} onChange={(e) => setData('number', e.target.value)} />
                        {errors.number && <p className="text-sm text-destructive">{errors.number}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="code">Code</Label>
                        <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="base_price">Base price override</Label>
                        <Input id="base_price" type="number" value={data.base_price} onChange={(e) => setData('base_price', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="max_occupancy">Max occupancy</Label>
                        <Input id="max_occupancy" type="number" value={data.max_occupancy} onChange={(e) => setData('max_occupancy', e.target.value)} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea id="description" rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </div>
                </CardContent>
            </Card>
            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save room' : 'Create room'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
