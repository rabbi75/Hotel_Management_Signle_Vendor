import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { BedRow, EnumOption, OptionMap } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    bed?: BedRow;
    hotels: OptionMap;
    rooms: OptionMap;
    floors: OptionMap;
    statuses: EnumOption[];
    defaultHotelId?: number | null;
}

export function BedForm({ bed, hotels, rooms, floors, statuses, defaultHotelId }: Props) {
    const editing = bed !== undefined;
    const form = useForm({
        hotel_id: String(bed?.hotel_id ?? defaultHotelId ?? ''),
        room_id: String(bed?.room_id ?? ''),
        floor_id: String(bed?.floor_id ?? ''),
        name: bed?.name ?? '',
        code: bed?.code ?? '',
        bed_type: bed?.bed_type ?? '',
        price: String(bed?.price ?? 0),
        description: bed?.description ?? '',
        status: bed?.status ?? 'available',
        is_active: bed?.is_active ?? true,
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : values.hotel_id,
            room_id: values.room_id === '' ? null : values.room_id,
            floor_id: values.floor_id === '' ? null : values.floor_id,
            price: Number(values.price),
        }));
        if (editing && bed) {
            form.put(route('beds.update', bed.id), { preserveScroll: true });
            return;
        }
        form.post(route('beds.store'));
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
                    <CardTitle>Bed</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label>Hotel *</Label>
                        <Select value={data.hotel_id || NONE} onValueChange={(v) => setData('hotel_id', v === NONE ? '' : v)}>
                            <SelectTrigger>
                                <SelectValue />
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
                    <div className="space-y-2">
                        <Label>Room *</Label>
                        <Select value={data.room_id || NONE} onValueChange={(v) => setData('room_id', v === NONE ? '' : v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(rooms).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.room_id && <p className="text-sm text-destructive">{errors.room_id}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="name">Name *</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                    </div>
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
                        <Label htmlFor="bed_type">Bed type</Label>
                        <Input id="bed_type" value={data.bed_type} onChange={(e) => setData('bed_type', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="price">Price</Label>
                        <Input id="price" type="number" value={data.price} onChange={(e) => setData('price', e.target.value)} />
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
                submitLabel={editing ? 'Save bed' : 'Create bed'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
