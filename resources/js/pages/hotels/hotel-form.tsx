import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { EnumOption, HotelRow } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

function Field({
    name,
    label,
    error,
    required = false,
    className,
    children,
}: {
    name: string;
    label: string;
    error?: string;
    required?: boolean;
    className?: string;
    children: (props: {
        id: string;
        'aria-invalid': true | undefined;
        required: boolean | undefined;
    }) => ReactNode;
}) {
    const id = `hotel-${name}`;

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                )}
            </Label>
            {children({ id, 'aria-invalid': error ? true : undefined, required: required || undefined })}
            {error && (
                <p className="flex items-start gap-1.5 text-sm text-destructive">
                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    {error}
                </p>
            )}
        </div>
    );
}

interface Props {
    hotel?: HotelRow;
    statuses: EnumOption[];
}

export function HotelForm({ hotel, statuses }: Props) {
    const editing = hotel !== undefined;
    const form = useForm({
        name: hotel?.name ?? '',
        description: hotel?.description ?? '',
        address: hotel?.address ?? '',
        city: hotel?.city ?? '',
        state: hotel?.state ?? '',
        country: hotel?.country ?? '',
        postal_code: hotel?.postal_code ?? '',
        phone: hotel?.phone ?? '',
        email: hotel?.email ?? '',
        website: hotel?.website ?? '',
        check_in_time: hotel?.check_in_time ?? '14:00',
        check_out_time: hotel?.check_out_time ?? '11:00',
        currency: hotel?.currency ?? 'USD',
        timezone: hotel?.timezone ?? 'UTC',
        tax_rate: String(hotel?.tax_rate ?? 0),
        tax_name: hotel?.tax_name ?? '',
        policies: hotel?.policies ?? '',
        contact_name: hotel?.contact_name ?? '',
        contact_phone: hotel?.contact_phone ?? '',
        contact_email: hotel?.contact_email ?? '',
        status: hotel?.status ?? 'active',
        is_active: hotel?.is_active ?? true,
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            tax_rate: values.tax_rate === '' ? 0 : Number(values.tax_rate),
            website: values.website === '' ? null : values.website,
            email: values.email === '' ? null : values.email,
            contact_email: values.contact_email === '' ? null : values.contact_email,
        }));

        if (editing && hotel) {
            form.put(route('hotels.update', hotel.uuid), { preserveScroll: true });
            return;
        }

        form.post(route('hotels.store'));
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This hotel could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Property details</CardTitle>
                    <CardDescription>Identity for this hotel, hostel, resort or guest house.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <Field name="name" label="Name" error={errors.name} required className="sm:col-span-2">
                        {(props) => <Input {...props} value={data.name} onChange={(e) => setData('name', e.target.value)} />}
                    </Field>
                    <div className="space-y-2 sm:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label htmlFor="hotel-description">Description</Label>
                            <AiAssistButton
                                action="hotel.booking_copy"
                                label="Write description"
                                subjectId={hotel?.id}
                                focus="description"
                                draft={{
                                    focus: 'description',
                                    name: data.name,
                                    city: data.city,
                                    country: data.country,
                                    address: data.address,
                                    description: data.description,
                                    policies: data.policies,
                                    check_in_time: data.check_in_time,
                                    check_out_time: data.check_out_time,
                                }}
                                onApply={(text) => setData('description', text)}
                                applyLabel="Use as description"
                            />
                        </div>
                        <Textarea
                            id="hotel-description"
                            rows={3}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            aria-invalid={errors.description ? true : undefined}
                        />
                        {errors.description && (
                            <p className="flex items-start gap-1.5 text-sm text-destructive">
                                <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                {errors.description}
                            </p>
                        )}
                    </div>
                    <Field name="status" label="Status" error={errors.status}>
                        {(props) => (
                            <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                <SelectTrigger {...props}>
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
                        )}
                    </Field>
                    <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
                        <Label htmlFor="is_active">Active</Label>
                        <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                    </div>
                    <Field name="address" label="Address" error={errors.address} className="sm:col-span-2">
                        {(props) => <Input {...props} value={data.address} onChange={(e) => setData('address', e.target.value)} />}
                    </Field>
                    <Field name="city" label="City" error={errors.city}>
                        {(props) => <Input {...props} value={data.city} onChange={(e) => setData('city', e.target.value)} />}
                    </Field>
                    <Field name="country" label="Country" error={errors.country}>
                        {(props) => <Input {...props} value={data.country} onChange={(e) => setData('country', e.target.value)} />}
                    </Field>
                    <Field name="phone" label="Phone" error={errors.phone}>
                        {(props) => <Input {...props} value={data.phone} onChange={(e) => setData('phone', e.target.value)} />}
                    </Field>
                    <Field name="email" label="Email" error={errors.email}>
                        {(props) => <Input {...props} type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />}
                    </Field>
                    <Field name="check_in_time" label="Check-in" error={errors.check_in_time}>
                        {(props) => <Input {...props} value={data.check_in_time} onChange={(e) => setData('check_in_time', e.target.value)} />}
                    </Field>
                    <Field name="check_out_time" label="Check-out" error={errors.check_out_time}>
                        {(props) => <Input {...props} value={data.check_out_time} onChange={(e) => setData('check_out_time', e.target.value)} />}
                    </Field>
                    <Field name="currency" label="Currency" error={errors.currency}>
                        {(props) => (
                            <Input
                                {...props}
                                maxLength={3}
                                value={data.currency}
                                onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                            />
                        )}
                    </Field>
                    <Field name="timezone" label="Timezone" error={errors.timezone}>
                        {(props) => <Input {...props} value={data.timezone} onChange={(e) => setData('timezone', e.target.value)} />}
                    </Field>
                    <Field name="tax_rate" label="Tax rate (%)" error={errors.tax_rate}>
                        {(props) => (
                            <Input {...props} type="number" step="0.01" value={data.tax_rate} onChange={(e) => setData('tax_rate', e.target.value)} />
                        )}
                    </Field>
                    <div className="space-y-2 sm:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label htmlFor="hotel-policies">Policies</Label>
                            <AiAssistButton
                                action="hotel.booking_copy"
                                label="Draft policies"
                                subjectId={hotel?.id}
                                focus="policies"
                                draft={{
                                    focus: 'policies',
                                    name: data.name,
                                    city: data.city,
                                    country: data.country,
                                    description: data.description,
                                    policies: data.policies,
                                    check_in_time: data.check_in_time,
                                    check_out_time: data.check_out_time,
                                }}
                                onApply={(text) => setData('policies', text)}
                                applyLabel="Use as policies"
                            />
                        </div>
                        <Textarea
                            id="hotel-policies"
                            rows={3}
                            value={data.policies}
                            onChange={(e) => setData('policies', e.target.value)}
                            aria-invalid={errors.policies ? true : undefined}
                        />
                        {errors.policies && (
                            <p className="flex items-start gap-1.5 text-sm text-destructive">
                                <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                {errors.policies}
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save hotel' : 'Create hotel'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
