import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { OptionMap, RoomTypeRow } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    roomType?: RoomTypeRow;
    hotels: OptionMap;
    facilities: OptionMap;
    defaultHotelId?: number | null;
}

export function RoomTypeForm({ roomType, hotels, facilities, defaultHotelId }: Props) {
    const editing = roomType !== undefined;
    const form = useForm({
        hotel_id: String(roomType?.hotel_id ?? defaultHotelId ?? ''),
        name: roomType?.name ?? '',
        code: roomType?.code ?? '',
        description: roomType?.description ?? '',
        base_price: String(roomType?.base_price ?? 0),
        max_adults: String(roomType?.max_adults ?? 2),
        max_children: String(roomType?.max_children ?? 0),
        max_occupancy: String(roomType?.max_occupancy ?? 2),
        bed_configuration: roomType?.bed_configuration ?? '',
        is_active: roomType?.is_active ?? true,
        facility_ids: (roomType?.facility_ids ?? []).map(String),
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : values.hotel_id,
            base_price: Number(values.base_price),
            max_adults: Number(values.max_adults),
            max_children: Number(values.max_children),
            max_occupancy: Number(values.max_occupancy),
            facility_ids: values.facility_ids.map(Number),
        }));
        if (editing && roomType) {
            form.put(route('room-types.update', roomType.id), { preserveScroll: true });
            return;
        }
        form.post(route('room-types.store'));
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
                    <CardTitle>Room type</CardTitle>
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
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="name">Name *</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="code">Code</Label>
                        <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="base_price">Base price (minor units)</Label>
                        <Input id="base_price" type="number" value={data.base_price} onChange={(e) => setData('base_price', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="max_adults">Max adults</Label>
                        <Input id="max_adults" type="number" value={data.max_adults} onChange={(e) => setData('max_adults', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="max_children">Max children</Label>
                        <Input id="max_children" type="number" value={data.max_children} onChange={(e) => setData('max_children', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="max_occupancy">Max occupancy</Label>
                        <Input id="max_occupancy" type="number" value={data.max_occupancy} onChange={(e) => setData('max_occupancy', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="bed_configuration">Bed configuration</Label>
                        <Input id="bed_configuration" value={data.bed_configuration} onChange={(e) => setData('bed_configuration', e.target.value)} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label htmlFor="description">Description</Label>
                            <AiAssistButton
                                action="room_type.marketing_copy"
                                label="Write with AI"
                                subjectId={roomType?.id}
                                draft={{
                                    name: data.name,
                                    code: data.code,
                                    description: data.description,
                                    base_price: data.base_price,
                                    max_adults: data.max_adults,
                                    max_children: data.max_children,
                                    max_occupancy: data.max_occupancy,
                                    bed_configuration: data.bed_configuration,
                                    facilities: data.facility_ids.map((id) => facilities[id]).filter(Boolean).join(', '),
                                }}
                                onApply={(text) => setData('description', text)}
                                applyLabel="Use as description"
                                description="Generate guest-facing room type copy for booking channels."
                            />
                        </div>
                        <Textarea id="description" rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </div>
                    {Object.keys(facilities).length > 0 && (
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Facilities</Label>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {Object.entries(facilities).map(([id, name]) => {
                                    const checked = data.facility_ids.includes(id);
                                    return (
                                        <label key={id} className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={(e) =>
                                                    setData(
                                                        'facility_ids',
                                                        e.target.checked
                                                            ? [...data.facility_ids, id]
                                                            : data.facility_ids.filter((v) => v !== id),
                                                    )
                                                }
                                            />
                                            {name}
                                        </label>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save room type' : 'Create room type'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
