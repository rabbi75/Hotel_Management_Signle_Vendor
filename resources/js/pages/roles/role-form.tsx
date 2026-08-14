import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { PermissionGroups, RoleSummary } from '@/types/roles';
import { useForm } from '@inertiajs/react';
import { CircleAlert, Lock } from 'lucide-react';
import { useMemo } from 'react';

interface RoleFormValues {
    name: string;
    permissions: string[];
    [key: string]: string | string[];
}

export interface RoleFormProps {
    /** Omitted when creating. */
    role?: RoleSummary;
    groups: PermissionGroups;
}

export function RoleForm({ role, groups }: RoleFormProps) {
    const editing = role !== undefined;

    const form = useForm<RoleFormValues>({
        name: role?.name ?? '',
        permissions: role?.permissions?.map((permission) => permission.name) ?? [],
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    const groupEntries = useMemo(
        () => Object.entries(groups).map(([key, group]) => ({ key, label: group.label, names: Object.keys(group.permissions), group })),
        [groups],
    );

    const selected = useMemo(() => new Set(data.permissions), [data.permissions]);
    const total = groupEntries.reduce((sum, entry) => sum + entry.names.length, 0);

    function toggle(name: string, checked: boolean): void {
        setData('permissions', checked ? [...new Set([...data.permissions, name])] : data.permissions.filter((entry) => entry !== name));
    }

    function toggleGroup(names: string[], checked: boolean): void {
        setData(
            'permissions',
            checked ? [...new Set([...data.permissions, ...names])] : data.permissions.filter((entry) => !names.includes(entry)),
        );
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (editing && role) {
            form.put(route('roles.update', role.id), { preserveScroll: true });

            return;
        }

        form.post(route('roles.store'), { preserveScroll: true });
    }

    const hasErrors = Object.keys(errors).length > 0;
    const nameDescribedBy = errors.name ? 'role-name-error' : 'role-name-hint';

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {hasErrors && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This role could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            {role?.is_system && (
                <Alert variant="warning">
                    <Lock className="size-4" aria-hidden="true" />
                    <AlertTitle>This is a system role</AlertTitle>
                    <AlertDescription>
                        The application assigns “{role.name}” by name. Renaming it will break those assignments, and it cannot be deleted.
                    </AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>The identifier the application uses to assign this role.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="max-w-sm space-y-2">
                        <Label htmlFor="role-name">
                            Name
                            <span className="text-destructive" aria-hidden="true">
                                *
                            </span>
                        </Label>
                        <Input
                            id="role-name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            aria-invalid={errors.name ? true : undefined}
                            aria-describedby={nameDescribedBy}
                            required
                            autoComplete="off"
                            spellCheck={false}
                        />
                        {errors.name ? (
                            <p id="role-name-error" className="flex items-start gap-1.5 text-sm text-destructive">
                                <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                {errors.name}
                            </p>
                        ) : (
                            <p id="role-name-hint" className="text-xs text-muted-foreground">
                                Lowercase letters, numbers and hyphens only — for example <code>content-editor</code>.
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Permissions</CardTitle>
                    <CardDescription>
                        <span aria-live="polite">
                            {selected.size} of {total} permissions granted.
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {errors.permissions && (
                        <p className="flex items-start gap-1.5 text-sm text-destructive" role="alert">
                            <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                            {errors.permissions}
                        </p>
                    )}

                    {groupEntries.map((entry, index) => {
                        const granted = entry.names.filter((name) => selected.has(name));
                        const state: boolean | 'indeterminate' =
                            granted.length === 0 ? false : granted.length === entry.names.length ? true : 'indeterminate';
                        const groupId = `permission-group-${entry.key}`;

                        return (
                            <fieldset key={entry.key} className="space-y-3">
                                {index > 0 && <Separator className="mb-6" />}

                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <legend className="contents">
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id={groupId}
                                                checked={state}
                                                onCheckedChange={(checked) => toggleGroup(entry.names, checked === true)}
                                                aria-label={`Grant every ${entry.label} permission`}
                                            />
                                            <Label htmlFor={groupId} className="text-sm font-semibold">
                                                {entry.label}
                                            </Label>
                                            <Badge variant="outline">
                                                {granted.length}/{entry.names.length}
                                            </Badge>
                                        </div>
                                    </legend>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="xs"
                                        onClick={() => toggleGroup(entry.names, granted.length !== entry.names.length)}
                                    >
                                        {granted.length === entry.names.length ? 'Clear group' : 'Select all'}
                                    </Button>
                                </div>

                                <div className="grid gap-2 sm:grid-cols-2">
                                    {entry.names.map((name) => {
                                        const id = `permission-${name}`;

                                        return (
                                            <div key={name} className="flex items-start gap-2 rounded-md border border-border p-3">
                                                <Checkbox
                                                    id={id}
                                                    checked={selected.has(name)}
                                                    onCheckedChange={(checked) => toggle(name, checked === true)}
                                                    className="mt-0.5"
                                                />
                                                <Label htmlFor={id} className="flex-col items-start gap-0.5 font-normal">
                                                    <span>{entry.group.permissions[name]}</span>
                                                    <span className="font-mono text-[11px] text-muted-foreground">{name}</span>
                                                </Label>
                                            </div>
                                        );
                                    })}
                                </div>
                            </fieldset>
                        );
                    })}
                </CardContent>
            </Card>

            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save role' : 'Create role'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
