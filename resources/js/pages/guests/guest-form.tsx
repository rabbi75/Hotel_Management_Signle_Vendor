import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { EnumOption, GuestRow, OptionMap } from '@/types/reservation';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    guest?: GuestRow;
    hotels: OptionMap;
    genders: EnumOption[];
    defaultHotelId?: number | null;
}

export function GuestForm({ guest, hotels, genders, defaultHotelId }: Props) {
    const editing = guest !== undefined;
    const form = useForm({
        hotel_id: String(guest?.hotel_id ?? defaultHotelId ?? ''),
        first_name: guest?.first_name ?? '',
        last_name: guest?.last_name ?? '',
        gender: guest?.gender ?? '',
        date_of_birth: guest?.date_of_birth ?? '',
        phone: guest?.phone ?? '',
        email: guest?.email ?? '',
        address: guest?.address ?? '',
        city: guest?.city ?? '',
        country: guest?.country ?? '',
        nationality: guest?.nationality ?? '',
        id_type: guest?.id_type ?? '',
        id_number: guest?.id_number ?? '',
        emergency_contact_name: guest?.emergency_contact_name ?? '',
        emergency_contact_phone: guest?.emergency_contact_phone ?? '',
        notes: guest?.notes ?? '',
        is_vip: guest?.is_vip ?? false,
        is_blacklisted: guest?.is_blacklisted ?? false,
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : values.hotel_id,
            gender: values.gender === '' ? null : values.gender,
            date_of_birth: values.date_of_birth === '' ? null : values.date_of_birth,
            email: values.email === '' ? null : values.email,
        }));
        if (editing && guest) {
            form.put(route('guests.update', guest.uuid), { preserveScroll: true });
            return;
        }
        form.post(route('guests.store'));
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
                    <CardTitle>Guest details</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="first_name">First name *</Label>
                        <Input id="first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                        {errors.first_name && <p className="text-sm text-destructive">{errors.first_name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="last_name">Last name *</Label>
                        <Input id="last_name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                        {errors.last_name && <p className="text-sm text-destructive">{errors.last_name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label>Gender</Label>
                        <Select value={data.gender || NONE} onValueChange={(v) => setData('gender', v === NONE ? '' : v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Optional" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Not specified</SelectItem>
                                {genders.map((g) => (
                                    <SelectItem key={g.value} value={g.value}>
                                        {g.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="date_of_birth">Date of birth</Label>
                        <Input id="date_of_birth" type="date" value={data.date_of_birth} onChange={(e) => setData('date_of_birth', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="phone">Phone</Label>
                        <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="address">Address</Label>
                        <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="city">City</Label>
                        <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="country">Country</Label>
                        <Input id="country" value={data.country} onChange={(e) => setData('country', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="nationality">Nationality</Label>
                        <Input id="nationality" value={data.nationality} onChange={(e) => setData('nationality', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="id_type">ID type</Label>
                        <Input id="id_type" value={data.id_type} onChange={(e) => setData('id_type', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="id_number">ID / passport number</Label>
                        <Input id="id_number" value={data.id_number} onChange={(e) => setData('id_number', e.target.value)} />
                    </div>
                    <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
                        <Label htmlFor="is_vip">VIP</Label>
                        <Switch id="is_vip" checked={data.is_vip} onCheckedChange={(v) => setData('is_vip', v)} />
                    </div>
                    <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
                        <Label htmlFor="is_blacklisted">Blacklisted</Label>
                        <Switch id="is_blacklisted" checked={data.is_blacklisted} onCheckedChange={(v) => setData('is_blacklisted', v)} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea id="notes" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                </CardContent>
            </Card>
            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save guest' : 'Create guest'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
