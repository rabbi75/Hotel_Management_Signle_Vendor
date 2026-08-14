import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { EnumOption, HotelServiceRow } from '@/types/folio';
import type { OptionMap } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    service?: HotelServiceRow;
    hotels: OptionMap;
    categories: EnumOption[];
    defaultHotelId?: number | null;
}

export function HotelServiceForm({ service, hotels, categories, defaultHotelId }: Props) {
    const editing = service !== undefined;
    const form = useForm({
        hotel_id: String(service?.hotel_id ?? defaultHotelId ?? ''),
        name: service?.name ?? '',
        code: service?.code ?? '',
        category: service?.category ?? 'other',
        price: String(service?.price ?? 0),
        tax_rate: String(service?.tax_rate ?? 0),
        description: service?.description ?? '',
        is_active: service?.is_active ?? true,
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : values.hotel_id,
            price: Number(values.price),
            tax_rate: Number(values.tax_rate),
        }));
        if (editing && service) {
            form.put(route('hotel-services.update', service.id), { preserveScroll: true });
            return;
        }
        form.post(route('hotel-services.store'));
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
                    <CardTitle>Service</CardTitle>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="space-y-2">
                        <Label>Hotel scope</Label>
                        <Select value={data.hotel_id || NONE} onValueChange={(v) => setData('hotel_id', v === NONE ? '' : v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Workspace-wide" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Workspace-wide</SelectItem>
                                {Object.entries(hotels).map(([id, name]) => (
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
                        <Label htmlFor="code">Code</Label>
                        <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label>Category</Label>
                        <Select value={data.category} onValueChange={(v) => setData('category', v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((cat) => (
                                    <SelectItem key={cat.value} value={cat.value}>
                                        {cat.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="price">Price (minor units)</Label>
                            <Input id="price" type="number" value={data.price} onChange={(e) => setData('price', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="tax_rate">Tax rate (%)</Label>
                            <Input id="tax_rate" type="number" value={data.tax_rate} onChange={(e) => setData('tax_rate', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea id="description" rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </div>
                </CardContent>
            </Card>
            <FormActions dirty={isDirty} submitting={processing} saved={recentlySuccessful} submitLabel={editing ? 'Save service' : 'Create service'} onCancel={() => form.reset()} />
        </form>
    );
}
