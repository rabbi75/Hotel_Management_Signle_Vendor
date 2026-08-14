import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { FacilityRow, OptionMap } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    facility?: FacilityRow;
    hotels: OptionMap;
    defaultHotelId?: number | null;
}

export function FacilityForm({ facility, hotels, defaultHotelId }: Props) {
    const editing = facility !== undefined;
    const form = useForm({
        hotel_id: String(facility?.hotel_id ?? ''),
        name: facility?.name ?? '',
        code: facility?.code ?? '',
        description: facility?.description ?? '',
        icon: facility?.icon ?? '',
        is_active: facility?.is_active ?? true,
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : values.hotel_id,
        }));
        if (editing && facility) {
            form.put(route('facilities.update', facility.id), { preserveScroll: true });
            return;
        }
        form.post(route('facilities.store'));
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
                    <CardTitle>Facility</CardTitle>
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
                        <p className="text-xs text-muted-foreground">Leave empty to share across all properties in this workspace.</p>
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
                        <Label htmlFor="icon">Icon</Label>
                        <Input id="icon" value={data.icon} onChange={(e) => setData('icon', e.target.value)} placeholder="wifi, pool, parking…" />
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
                submitLabel={editing ? 'Save facility' : 'Create facility'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
