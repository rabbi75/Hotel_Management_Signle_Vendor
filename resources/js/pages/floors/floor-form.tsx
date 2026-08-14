import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { FloorRow, OptionMap } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    floor?: FloorRow;
    hotels: OptionMap;
    buildings: OptionMap;
    defaultHotelId?: number | null;
}

export function FloorForm({ floor, hotels, buildings, defaultHotelId }: Props) {
    const editing = floor !== undefined;
    const form = useForm({
        hotel_id: String(floor?.hotel_id ?? defaultHotelId ?? ''),
        building_id: String(floor?.building_id ?? ''),
        name: floor?.name ?? '',
        floor_number: String(floor?.floor_number ?? 0),
        code: floor?.code ?? '',
        description: floor?.description ?? '',
        is_active: floor?.is_active ?? true,
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : values.hotel_id,
            building_id: values.building_id === '' ? null : values.building_id,
            floor_number: Number(values.floor_number),
        }));
        if (editing && floor) {
            form.put(route('floors.update', floor.id), { preserveScroll: true });
            return;
        }
        form.post(route('floors.store'));
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
                    <CardTitle>Floor</CardTitle>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="space-y-2">
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
                    <div className="space-y-2">
                        <Label>Building</Label>
                        <Select value={data.building_id || NONE} onValueChange={(v) => setData('building_id', v === NONE ? '' : v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Optional" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No building</SelectItem>
                                {Object.entries(buildings).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="name">Name *</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="floor_number">Floor number *</Label>
                        <Input
                            id="floor_number"
                            type="number"
                            value={data.floor_number}
                            onChange={(e) => setData('floor_number', e.target.value)}
                        />
                        {errors.floor_number && <p className="text-sm text-destructive">{errors.floor_number}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="code">Code</Label>
                        <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea id="description" rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </div>
                </CardContent>
            </Card>
            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save floor' : 'Create floor'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
