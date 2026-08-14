import { FormActions } from '@/components/forms/form-actions';
import { ImageUpload } from '@/components/forms/image-upload';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { LocaleDefinition } from '@/types';
import type { Company, CompanyFormValues } from '@/types/companies';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useId, type ReactNode } from 'react';

interface FieldControlProps {
    id: string;
    'aria-invalid': true | undefined;
    'aria-describedby': string | undefined;
    required: boolean | undefined;
}

function Field({
    name,
    label,
    error,
    hint,
    required = false,
    className,
    children,
}: {
    name: string;
    label: string;
    error?: string | undefined;
    hint?: string;
    required?: boolean;
    className?: string;
    children: (props: FieldControlProps) => ReactNode;
}) {
    const id = `workspace-${name}`;
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const describedBy = [error ? errorId : null, hint ? hintId : null].filter(Boolean).join(' ') || undefined;

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

            {children({ id, 'aria-invalid': error ? true : undefined, 'aria-describedby': describedBy, required: required || undefined })}

            {hint && !error && (
                <p id={hintId} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            {error && (
                <p id={errorId} className="flex items-start gap-1.5 text-sm text-destructive">
                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    {error}
                </p>
            )}
        </div>
    );
}

function supportedTimezones(): string[] {
    const intl = Intl as typeof Intl & { supportedValuesOf?: (key: string) => string[] };

    try {
        return intl.supportedValuesOf?.('timeZone') ?? [];
    } catch {
        return [];
    }
}

interface WorkspaceFormValues extends CompanyFormValues {
    logo: File | null;
    remove_logo: boolean;
    _method?: string;
    [key: string]: string | boolean | File | null | undefined;
}

export interface CompanyFormProps {
    /** Omitted when creating. */
    company?: Company;
    defaults?: { timezone: string; currency: string; locale: string };
    locales: Record<string, LocaleDefinition>;
}

export function CompanyForm({ company, defaults, locales }: CompanyFormProps) {
    const editing = company !== undefined;
    const timezoneListId = useId();
    const zones = supportedTimezones();

    const form = useForm<WorkspaceFormValues>({
        name: company?.name ?? '',
        email: company?.email ?? '',
        phone: company?.phone ?? '',
        website: company?.website ?? '',
        tax_id: company?.tax_id ?? '',
        address_line_1: company?.address_line_1 ?? '',
        address_line_2: company?.address_line_2 ?? '',
        city: company?.city ?? '',
        state: company?.state ?? '',
        postal_code: company?.postal_code ?? '',
        country_code: company?.country_code ?? '',
        timezone: company?.timezone ?? defaults?.timezone ?? 'UTC',
        currency: company?.currency ?? defaults?.currency ?? 'USD',
        locale: company?.locale ?? defaults?.locale ?? Object.keys(locales)[0] ?? 'en',
        logo: null,
        remove_logo: false,
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (editing && company) {
            // The logo is a file, so the update has to travel as multipart —
            // which means POST plus a method override rather than a real PUT.
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(route('companies.update', company.uuid), { preserveScroll: true, forceFormData: true });

            return;
        }

        form.post(route('companies.store'));
    }

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {hasErrors && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This workspace could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Profile</CardTitle>
                    <CardDescription>How this workspace identifies itself on documents and invitations.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <Field name="name" label="Workspace name" error={errors.name} required className="sm:col-span-2">
                        {(props) => <Input {...props} value={data.name} onChange={(event) => setData('name', event.target.value)} />}
                    </Field>

                    <Field name="email" label="Contact email" error={errors.email}>
                        {(props) => (
                            <Input {...props} type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} />
                        )}
                    </Field>

                    <Field name="phone" label="Phone" error={errors.phone}>
                        {(props) => (
                            <Input {...props} type="tel" value={data.phone} onChange={(event) => setData('phone', event.target.value)} />
                        )}
                    </Field>

                    <Field name="website" label="Website" error={errors.website} hint="Include the scheme, e.g. https://example.com">
                        {(props) => (
                            <Input
                                {...props}
                                type="url"
                                value={data.website}
                                onChange={(event) => setData('website', event.target.value)}
                            />
                        )}
                    </Field>

                    <Field name="tax_id" label="Tax ID" error={errors.tax_id}>
                        {(props) => <Input {...props} value={data.tax_id} onChange={(event) => setData('tax_id', event.target.value)} />}
                    </Field>

                    {editing && (
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="workspace-logo-input">Logo</Label>
                            <ImageUpload
                                value={data.logo ?? (data.remove_logo ? null : (company?.logo ?? null))}
                                onChange={(file) => {
                                    setData('logo', file);
                                    setData('remove_logo', file === null);
                                }}
                                label="Workspace logo"
                                shape="square"
                                invalid={Boolean(errors.logo)}
                                describedBy={errors.logo ? 'workspace-logo-error' : undefined}
                            />
                            {errors.logo && (
                                <p id="workspace-logo-error" className="text-sm text-destructive">
                                    {errors.logo}
                                </p>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Address</CardTitle>
                    <CardDescription>Used on invoices and other generated documents.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <Field name="address_line_1" label="Address line 1" error={errors.address_line_1} className="sm:col-span-2">
                        {(props) => (
                            <Input
                                {...props}
                                value={data.address_line_1}
                                onChange={(event) => setData('address_line_1', event.target.value)}
                                autoComplete="address-line1"
                            />
                        )}
                    </Field>

                    <Field name="address_line_2" label="Address line 2" error={errors.address_line_2} className="sm:col-span-2">
                        {(props) => (
                            <Input
                                {...props}
                                value={data.address_line_2}
                                onChange={(event) => setData('address_line_2', event.target.value)}
                                autoComplete="address-line2"
                            />
                        )}
                    </Field>

                    <Field name="city" label="City" error={errors.city}>
                        {(props) => (
                            <Input
                                {...props}
                                value={data.city}
                                onChange={(event) => setData('city', event.target.value)}
                                autoComplete="address-level2"
                            />
                        )}
                    </Field>

                    <Field name="state" label="State or region" error={errors.state}>
                        {(props) => (
                            <Input
                                {...props}
                                value={data.state}
                                onChange={(event) => setData('state', event.target.value)}
                                autoComplete="address-level1"
                            />
                        )}
                    </Field>

                    <Field name="postal_code" label="Postal code" error={errors.postal_code}>
                        {(props) => (
                            <Input
                                {...props}
                                value={data.postal_code}
                                onChange={(event) => setData('postal_code', event.target.value)}
                                autoComplete="postal-code"
                            />
                        )}
                    </Field>

                    <Field name="country_code" label="Country code" error={errors.country_code} hint="Two-letter ISO 3166-1 code, e.g. DE.">
                        {(props) => (
                            <Input
                                {...props}
                                value={data.country_code}
                                onChange={(event) => setData('country_code', event.target.value.toUpperCase().slice(0, 2))}
                                maxLength={2}
                                className="uppercase"
                            />
                        )}
                    </Field>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Localisation</CardTitle>
                    <CardDescription>Defaults for dates, money and the interface language.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-3">
                    <Field name="timezone" label="Timezone" error={errors.timezone} required hint="IANA identifier, e.g. Europe/Berlin.">
                        {(props) => (
                            <>
                                <Input
                                    {...props}
                                    list={timezoneListId}
                                    value={data.timezone}
                                    onChange={(event) => setData('timezone', event.target.value)}
                                    autoComplete="off"
                                />
                                <datalist id={timezoneListId}>
                                    {zones.map((zone) => (
                                        <option key={zone} value={zone} />
                                    ))}
                                </datalist>
                            </>
                        )}
                    </Field>

                    <Field name="currency" label="Currency" error={errors.currency} required hint="Three-letter ISO 4217 code.">
                        {(props) => (
                            <Input
                                {...props}
                                value={data.currency}
                                onChange={(event) => setData('currency', event.target.value.toUpperCase().slice(0, 3))}
                                maxLength={3}
                                className="uppercase"
                            />
                        )}
                    </Field>

                    <Field name="locale" label="Language" error={errors.locale} required>
                        {(props) => (
                            <Select value={data.locale} onValueChange={(value) => setData('locale', value)}>
                                <SelectTrigger {...props}>
                                    <SelectValue placeholder="Select a language" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(locales).map(([code, locale]) => (
                                        <SelectItem key={code} value={code}>
                                            {locale.native}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                </CardContent>
            </Card>

            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save workspace' : 'Create workspace'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
