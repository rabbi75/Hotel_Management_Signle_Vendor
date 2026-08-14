import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import type { UserFormOptions, UserFormValues, UserRow } from '@/types/users';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useId, type ReactNode } from 'react';

export interface FieldControlProps {
    id: string;
    'aria-invalid': true | undefined;
    'aria-describedby': string | undefined;
    required: boolean | undefined;
}

export interface FieldProps {
    name: string;
    label: string;
    error?: string | undefined;
    hint?: string;
    required?: boolean;
    className?: string;
    children: (props: FieldControlProps) => ReactNode;
}

/**
 * Label, control and message wired together.
 *
 * The control receives its own `aria-describedby` rather than the wrapper
 * setting it, because only the control knows whether it renders a real input.
 */
export function Field({ name, label, error, hint, required = false, className, children }: FieldProps) {
    const id = `field-${name}`;
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

/** A datalist-backed timezone picker; the browser handles filtering 400+ zones. */
function TimezoneField({ value, onChange, error }: { value: string; onChange: (value: string) => void; error?: string | undefined }) {
    const listId = useId();
    const zones = supportedTimezones();

    return (
        <Field name="timezone" label="Timezone" error={error} required hint="IANA identifier, e.g. Europe/Berlin.">
            {(props) => (
                <>
                    <Input {...props} list={listId} value={value} onChange={(event) => onChange(event.target.value)} autoComplete="off" />
                    <datalist id={listId}>
                        {zones.map((zone) => (
                            <option key={zone} value={zone} />
                        ))}
                    </datalist>
                </>
            )}
        </Field>
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

export interface UserFormProps extends UserFormOptions {
    /** Omitted when creating. */
    user?: UserRow;
}

export function UserForm({ user, roles, statuses, company_roles: companyRoles, themes, locales }: UserFormProps) {
    const { can } = usePermissions();
    const editing = user !== undefined;
    const mayAssignRoles = can('roles.assign');

    const form = useForm<UserFormValues>({
        first_name: user?.first_name ?? '',
        last_name: user?.last_name ?? '',
        email: user?.email ?? '',
        password: '',
        phone: user?.phone ?? '',
        job_title: user?.job_title ?? '',
        bio: user?.bio ?? '',
        status: user?.status.value ?? statuses[0]?.value ?? 'active',
        company_role: user?.companies?.[0]?.role ?? companyRoles[companyRoles.length - 1]?.value ?? 'member',
        timezone: user?.timezone ?? 'UTC',
        locale: user?.locale ?? Object.keys(locales)[0] ?? 'en',
        theme: user?.theme ?? 'system',
        roles: user?.roles?.map((role) => role.name) ?? [],
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        // An empty password means "leave it to the server"; the rules reject an
        // empty string but accept a missing value.
        form.transform((values) => ({ ...values, password: values.password === '' ? null : values.password }));

        if (editing && user) {
            form.put(route('users.update', user.id), { preserveScroll: true });

            return;
        }

        form.post(route('users.store'), { preserveScroll: true });
    }

    function toggleRole(name: string, checked: boolean): void {
        setData('roles', checked ? [...new Set([...data.roles, name])] : data.roles.filter((entry) => entry !== name));
    }

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {hasErrors && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This form could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Identity</CardTitle>
                    <CardDescription>How this person appears across the workspace.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <Field name="first_name" label="First name" error={errors.first_name} required>
                        {(props) => (
                            <Input
                                {...props}
                                value={data.first_name}
                                onChange={(event) => setData('first_name', event.target.value)}
                                autoComplete="given-name"
                            />
                        )}
                    </Field>

                    <Field name="last_name" label="Last name" error={errors.last_name} required>
                        {(props) => (
                            <Input
                                {...props}
                                value={data.last_name}
                                onChange={(event) => setData('last_name', event.target.value)}
                                autoComplete="family-name"
                            />
                        )}
                    </Field>

                    <Field name="email" label="Email address" error={errors.email} required>
                        {(props) => (
                            <Input
                                {...props}
                                type="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                autoComplete="email"
                            />
                        )}
                    </Field>

                    <Field name="phone" label="Phone" error={errors.phone}>
                        {(props) => (
                            <Input {...props} type="tel" value={data.phone} onChange={(event) => setData('phone', event.target.value)} />
                        )}
                    </Field>

                    <Field name="job_title" label="Job title" error={errors.job_title}>
                        {(props) => (
                            <Input {...props} value={data.job_title} onChange={(event) => setData('job_title', event.target.value)} />
                        )}
                    </Field>

                    <Field
                        name="password"
                        label="Password"
                        error={errors.password}
                        hint={editing ? 'Leave blank to keep the current password.' : 'Leave blank to email an invitation instead.'}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                type="password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                autoComplete="new-password"
                            />
                        )}
                    </Field>

                    <Field name="bio" label="Bio" error={errors.bio} className="sm:col-span-2">
                        {(props) => (
                            <Textarea {...props} rows={3} value={data.bio} onChange={(event) => setData('bio', event.target.value)} />
                        )}
                    </Field>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Access</CardTitle>
                    <CardDescription>Account state, workspace standing and assigned roles.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field name="status" label="Status" error={errors.status} required>
                            {(props) => (
                                <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                    <SelectTrigger {...props}>
                                        <SelectValue placeholder="Select a status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((status) => (
                                            <SelectItem key={status.value} value={status.value}>
                                                {status.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>

                        <Field name="company_role" label="Workspace role" error={errors.company_role} required>
                            {(props) => (
                                <Select value={data.company_role} onValueChange={(value) => setData('company_role', value)}>
                                    <SelectTrigger {...props}>
                                        <SelectValue placeholder="Select a workspace role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {companyRoles.map((role) => (
                                            <SelectItem key={role.value} value={role.value}>
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    </div>

                    {mayAssignRoles && (
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium">Permission roles</legend>
                            {errors.roles && (
                                <p className="flex items-start gap-1.5 text-sm text-destructive" role="alert">
                                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    {errors.roles}
                                </p>
                            )}
                            {roles.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No assignable roles exist yet.</p>
                            ) : (
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {roles.map((name) => {
                                        const id = `role-${name}`;

                                        return (
                                            <div key={name} className="flex items-center gap-2 rounded-md border border-border p-3">
                                                <Checkbox
                                                    id={id}
                                                    checked={data.roles.includes(name)}
                                                    onCheckedChange={(checked) => toggleRole(name, checked === true)}
                                                />
                                                <Label htmlFor={id} className="font-normal">
                                                    {name}
                                                </Label>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </fieldset>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Localisation</CardTitle>
                    <CardDescription>Defaults applied to dates, numbers and the interface.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-3">
                    <TimezoneField value={data.timezone} onChange={(value) => setData('timezone', value)} error={errors.timezone} />

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

                    <Field name="theme" label="Theme" error={errors.theme}>
                        {(props) => (
                            <Select value={data.theme} onValueChange={(value) => setData('theme', value)}>
                                <SelectTrigger {...props}>
                                    <SelectValue placeholder="Select a theme" />
                                </SelectTrigger>
                                <SelectContent>
                                    {themes.map((theme) => (
                                        <SelectItem key={theme.value} value={theme.value}>
                                            {theme.label}
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
                submitLabel={editing ? 'Save changes' : 'Create user'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
