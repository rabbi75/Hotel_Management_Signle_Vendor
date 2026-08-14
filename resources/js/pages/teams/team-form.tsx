import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ColorPicker } from '@/components/ui/color-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { OptionMap, TeamRow } from '@/types/companies';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';

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
    const id = `team-${name}`;
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

interface TeamFormValues {
    name: string;
    description: string;
    color: string;
    department_id: string;
    lead_id: string;
    member_ids: string[];
    [key: string]: string | string[];
}

export interface TeamFormProps {
    /** Omitted when creating. */
    team?: TeamRow;
    departments: OptionMap;
    /** Workspace members, keyed by user id. */
    members: OptionMap;
}

export function TeamForm({ team, departments, members }: TeamFormProps) {
    const editing = team !== undefined;
    const [memberFilter, setMemberFilter] = useState('');

    const form = useForm<TeamFormValues>({
        name: team?.name ?? '',
        description: team?.description ?? '',
        color: team?.color ?? '',
        department_id: String(team?.department_id ?? ''),
        lead_id: String(team?.lead_id ?? ''),
        member_ids: (team?.members ?? []).map((member) => String(member.id)),
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    const memberEntries = useMemo(() => Object.entries(members), [members]);
    const filteredMembers = useMemo(() => {
        const needle = memberFilter.trim().toLowerCase();

        return needle === '' ? memberEntries : memberEntries.filter(([, name]) => name.toLowerCase().includes(needle));
    }, [memberEntries, memberFilter]);

    function toggleMember(id: string, checked: boolean): void {
        setData('member_ids', checked ? [...new Set([...data.member_ids, id])] : data.member_ids.filter((entry) => entry !== id));
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        // Empty optional fields must travel as null: the server's rules accept a
        // missing value but reject an empty string.
        form.transform((values) => ({
            ...values,
            department_id: values.department_id === '' ? null : values.department_id,
            lead_id: values.lead_id === '' ? null : values.lead_id,
            color: values.color === '' ? null : values.color,
        }));

        if (editing && team) {
            form.put(route('teams.update', team.id), { preserveScroll: true });

            return;
        }

        form.post(route('teams.store'));
    }

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {hasErrors && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This team could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>Teams group people around a shared piece of work.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <Field name="name" label="Name" error={errors.name} required>
                        {(props) => <Input {...props} value={data.name} onChange={(event) => setData('name', event.target.value)} />}
                    </Field>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field name="department" label="Department" error={errors.department_id}>
                            {(props) => (
                                <Select
                                    value={data.department_id === '' ? NONE : data.department_id}
                                    onValueChange={(value) => setData('department_id', value === NONE ? '' : value)}
                                >
                                    <SelectTrigger {...props}>
                                        <SelectValue placeholder="No department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>No department</SelectItem>
                                        {Object.entries(departments).map(([id, name]) => (
                                            <SelectItem key={id} value={id}>
                                                {name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>

                        <Field name="lead" label="Team lead" error={errors.lead_id}>
                            {(props) => (
                                <Select
                                    value={data.lead_id === '' ? NONE : data.lead_id}
                                    onValueChange={(value) => setData('lead_id', value === NONE ? '' : value)}
                                >
                                    <SelectTrigger {...props}>
                                        <SelectValue placeholder="No lead" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>No lead</SelectItem>
                                        {memberEntries.map(([id, name]) => (
                                            <SelectItem key={id} value={id}>
                                                {name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    </div>

                    <div className="space-y-2">
                        <span id="team-color-label" className="text-sm font-medium">
                            Colour
                        </span>
                        <div
                            role="group"
                            aria-labelledby="team-color-label"
                            aria-describedby={errors.color ? 'team-color-error' : undefined}
                        >
                            <ColorPicker value={data.color} onChange={(hex) => setData('color', hex)} />
                        </div>
                        {errors.color && (
                            <p id="team-color-error" className="text-sm text-destructive">
                                {errors.color}
                            </p>
                        )}
                    </div>

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

            <Card>
                <CardHeader>
                    <CardTitle>Members</CardTitle>
                    <CardDescription>
                        <span aria-live="polite">
                            {data.member_ids.length} of {memberEntries.length} workspace members selected.
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {memberEntries.length === 0 ? (
                        <p className="text-sm text-muted-foreground">This workspace has no other members yet.</p>
                    ) : (
                        <>
                            <div className="flex flex-wrap items-center gap-2">
                                <Label htmlFor="team-member-filter" className="sr-only">
                                    Filter members
                                </Label>
                                <Input
                                    id="team-member-filter"
                                    value={memberFilter}
                                    onChange={(event) => setMemberFilter(event.target.value)}
                                    placeholder="Filter members…"
                                    className="max-w-xs"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setData(
                                            'member_ids',
                                            memberEntries.map(([id]) => id),
                                        )
                                    }
                                    disabled={data.member_ids.length === memberEntries.length}
                                >
                                    Select all
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setData('member_ids', [])}
                                    disabled={data.member_ids.length === 0}
                                >
                                    Clear
                                </Button>
                            </div>

                            {errors.member_ids && (
                                <p className="flex items-start gap-1.5 text-sm text-destructive" role="alert">
                                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    {errors.member_ids}
                                </p>
                            )}

                            {filteredMembers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No member matches “{memberFilter}”.</p>
                            ) : (
                                <ScrollArea className="max-h-72">
                                    <fieldset className="grid gap-2 pr-3 sm:grid-cols-2">
                                        <legend className="sr-only">Team members</legend>
                                        {filteredMembers.map(([id, name]) => {
                                            const inputId = `team-member-${id}`;

                                            return (
                                                <div key={id} className="flex items-center gap-2 rounded-md border border-border p-2.5">
                                                    <Checkbox
                                                        id={inputId}
                                                        checked={data.member_ids.includes(id)}
                                                        onCheckedChange={(checked) => toggleMember(id, checked === true)}
                                                    />
                                                    <Label htmlFor={inputId} className="min-w-0 font-normal">
                                                        <span className="truncate">{name}</span>
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </fieldset>
                                </ScrollArea>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save team' : 'Create team'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
