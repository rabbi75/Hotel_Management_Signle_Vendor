import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { DepartmentNode, OptionMap } from '@/types/companies';
import { useForm } from '@inertiajs/react';
import { CircleAlert, UserRoundMinus } from 'lucide-react';
import type { ReactNode } from 'react';

/** Radix Select cannot hold an empty string value, so "none" needs a sentinel. */
const NONE = '__none__';

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
    const id = `department-${name}`;
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

interface DepartmentFormValues {
    name: string;
    description: string;
    parent_id: string;
    manager_id: string;
    [key: string]: string;
}

export interface DepartmentFormProps {
    /** Omitted when creating. */
    department?: DepartmentNode;
    departments: OptionMap;
    /** Preselected parent, used by the "add a child department" affordance. */
    defaultParentId?: string;
}

export function DepartmentForm({ department, departments, defaultParentId }: DepartmentFormProps) {
    const editing = department !== undefined;

    const form = useForm<DepartmentFormValues>({
        name: department?.name ?? '',
        description: department?.description ?? '',
        parent_id: String(department?.parent_id ?? defaultParentId ?? ''),
        // Carried through unchanged: DepartmentData preserves nulls, so omitting
        // it here would silently unassign the manager on every save.
        manager_id: String(department?.manager_id ?? ''),
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.transform((values) => ({
            ...values,
            parent_id: values.parent_id === '' ? null : values.parent_id,
            manager_id: values.manager_id === '' ? null : values.manager_id,
        }));

        if (editing && department) {
            form.put(route('departments.update', department.id), { preserveScroll: true });

            return;
        }

        form.post(route('departments.store'));
    }

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {hasErrors && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This department could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>Departments group teams and people into a reporting structure.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <Field name="name" label="Name" error={errors.name} required>
                        {(props) => <Input {...props} value={data.name} onChange={(event) => setData('name', event.target.value)} />}
                    </Field>

                    <Field
                        name="parent"
                        label="Parent department"
                        error={errors.parent_id}
                        hint="Leave empty to place this department at the top level."
                    >
                        {(props) => (
                            <Select
                                value={data.parent_id === '' ? NONE : data.parent_id}
                                onValueChange={(value) => setData('parent_id', value === NONE ? '' : value)}
                            >
                                <SelectTrigger {...props}>
                                    <SelectValue placeholder="No parent" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>No parent (top level)</SelectItem>
                                    {Object.entries(departments).map(([id, name]) => (
                                        <SelectItem key={id} value={id}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>

                    <Field name="description" label="Description" error={errors.description}>
                        {(props) => (
                            <Textarea
                                {...props}
                                rows={3}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                            />
                        )}
                    </Field>
                </CardContent>
            </Card>

            {editing && (
                <Card>
                    <CardHeader>
                        <CardTitle>Manager</CardTitle>
                        <CardDescription>Who runs this department.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/*
                            The controller does not send a member list to this screen, so a
                            manager can be cleared but not chosen here. Assign one from the
                            members screen instead.
                        */}
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="min-w-0 text-sm">
                                {data.manager_id === '' ? (
                                    <span className="text-muted-foreground">No manager assigned.</span>
                                ) : (
                                    <>
                                        Currently managed by{' '}
                                        <span className="font-medium">{department?.manager ?? `member #${data.manager_id}`}</span>.
                                    </>
                                )}
                            </p>

                            {data.manager_id !== '' && (
                                <Button type="button" variant="outline" size="sm" onClick={() => setData('manager_id', '')}>
                                    <UserRoundMinus className="size-4" aria-hidden="true" />
                                    Clear manager
                                </Button>
                            )}
                        </div>

                        {errors.manager_id && (
                            <p className="mt-2 flex items-start gap-1.5 text-sm text-destructive">
                                <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                {errors.manager_id}
                            </p>
                        )}
                    </CardContent>
                </Card>
            )}

            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save department' : 'Create department'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
