import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ColorPicker } from '@/components/ui/color-picker';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { TimePicker } from '@/components/ui/time-picker';
import { cn } from '@/lib/utils';
import { Plus, Trash2 } from 'lucide-react';
import { useId } from 'react';
import { Controller, useFieldArray, useFormContext, useFormState, type FieldError } from 'react-hook-form';
import { FileUpload } from './file-upload';
import { ImageUpload } from './image-upload';
import { isFieldVisible, type FormFieldSchema, type FormValues } from './schema';

function errorMessage(error: unknown): string | null {
    if (!error || typeof error !== 'object') {
        return null;
    }

    const message = (error as FieldError).message;

    return typeof message === 'string' && message !== '' ? message : null;
}

export interface FormFieldRendererProps {
    field: FormFieldSchema;
    /** Prefix applied to `field.name`, set by the repeater for its rows. */
    namePrefix?: string;
    values: FormValues;
}

/** Renders one schema field, wired to the surrounding react-hook-form context. */
export function FormFieldRenderer({ field, namePrefix, values }: FormFieldRendererProps) {
    const { control, getFieldState } = useFormContext();
    const formState = useFormState({ control });
    const generatedId = useId();

    if (!isFieldVisible(field.showWhen, values)) {
        return null;
    }

    const name = namePrefix ? `${namePrefix}.${field.name}` : field.name;

    if (field.type === 'section') {
        return (
            <fieldset className="col-span-full space-y-4 rounded-lg border border-border p-4">
                <legend className="px-1 text-sm font-semibold">{field.label}</legend>
                {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                <div className="grid gap-4 sm:grid-cols-2">
                    {field.fields.map((child) => (
                        <FormFieldRenderer key={child.name} field={child} {...(namePrefix ? { namePrefix } : {})} values={values} />
                    ))}
                </div>
            </fieldset>
        );
    }

    if (field.type === 'repeater') {
        return <RepeaterControl field={field} name={name} values={values} />;
    }

    const fieldId = `${generatedId}-${name}`;
    const descriptionId = `${fieldId}-description`;
    const messageId = `${fieldId}-message`;
    const error = errorMessage(getFieldState(name, formState).error);
    const describedBy = [field.description ? descriptionId : null, error ? messageId : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('grid gap-2', field.span === 2 && 'sm:col-span-2')}>
            <Label htmlFor={fieldId} className={cn(error && 'text-destructive')}>
                {field.label}
                {field.required && (
                    <span aria-hidden="true" className="text-destructive">
                        *
                    </span>
                )}
                {field.required && <span className="sr-only">(required)</span>}
            </Label>

            <Controller
                control={control}
                name={name}
                render={({ field: controller }) => (
                    <Control
                        schema={field}
                        id={fieldId}
                        invalid={Boolean(error)}
                        describedBy={describedBy}
                        value={controller.value}
                        onChange={controller.onChange}
                        onBlur={controller.onBlur}
                    />
                )}
            />

            {field.description && (
                <p id={descriptionId} className="text-sm text-muted-foreground">
                    {field.description}
                </p>
            )}
            {error && (
                <p id={messageId} className="text-sm font-medium text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

interface ControlProps {
    schema: Exclude<FormFieldSchema, { type: 'section' } | { type: 'repeater' }>;
    id: string;
    invalid: boolean;
    describedBy: string | undefined;
    value: unknown;
    onChange: (value: unknown) => void;
    onBlur: () => void;
}

function Control({ schema, id, invalid, describedBy, value, onChange, onBlur }: ControlProps) {
    const shared = {
        id,
        'aria-invalid': invalid,
        'aria-describedby': describedBy,
        disabled: schema.disabled,
    } as const;

    switch (schema.type) {
        case 'text':
        case 'email':
        case 'password':
            return (
                <Input
                    {...shared}
                    type={schema.type}
                    autoComplete={schema.autoComplete}
                    placeholder={schema.placeholder}
                    value={typeof value === 'string' ? value : ''}
                    onChange={(event) => onChange(event.target.value)}
                    onBlur={onBlur}
                />
            );

        case 'number':
            return (
                <Input
                    {...shared}
                    type="number"
                    min={schema.min}
                    max={schema.max}
                    step={schema.step}
                    placeholder={schema.placeholder}
                    value={typeof value === 'number' || typeof value === 'string' ? value : ''}
                    onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
                    onBlur={onBlur}
                />
            );

        case 'textarea':
            return (
                <Textarea
                    {...shared}
                    rows={schema.rows ?? 4}
                    placeholder={schema.placeholder}
                    value={typeof value === 'string' ? value : ''}
                    onChange={(event) => onChange(event.target.value)}
                    onBlur={onBlur}
                />
            );

        case 'select':
            return (
                <Select
                    value={value === null || value === undefined ? '' : String(value)}
                    onValueChange={onChange}
                    disabled={schema.disabled}
                >
                    <SelectTrigger id={id} aria-invalid={invalid} aria-describedby={describedBy}>
                        <SelectValue placeholder={schema.placeholder ?? 'Select…'} />
                    </SelectTrigger>
                    <SelectContent>
                        {schema.options.map((option) => (
                            <SelectItem key={option.value} value={String(option.value)} disabled={option.disabled}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );

        case 'multiselect': {
            const selected = Array.isArray(value) ? value.map(String) : [];

            return (
                <div role="group" aria-invalid={invalid} aria-describedby={describedBy} className="grid gap-2 sm:grid-cols-2">
                    {schema.options.map((option) => {
                        const optionId = `${id}-${option.value}`;
                        const checked = selected.includes(String(option.value));

                        return (
                            <div key={option.value} className="flex items-center gap-2">
                                <Checkbox
                                    id={optionId}
                                    checked={checked}
                                    disabled={schema.disabled ?? option.disabled}
                                    onCheckedChange={(next) =>
                                        onChange(
                                            next === true
                                                ? [...selected, String(option.value)]
                                                : selected.filter((entry) => entry !== String(option.value)),
                                        )
                                    }
                                />
                                <Label htmlFor={optionId} className="text-sm font-normal">
                                    {option.label}
                                </Label>
                            </div>
                        );
                    })}
                </div>
            );
        }

        case 'radio':
            return (
                <RadioGroup
                    value={value === null || value === undefined ? '' : String(value)}
                    onValueChange={onChange}
                    disabled={schema.disabled}
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                >
                    {schema.options.map((option) => {
                        const optionId = `${id}-${option.value}`;

                        return (
                            <div key={option.value} className="flex items-start gap-2">
                                <RadioGroupItem id={optionId} value={String(option.value)} disabled={option.disabled} className="mt-0.5" />
                                <Label htmlFor={optionId} className="grid gap-0.5 text-sm font-normal">
                                    {option.label}
                                    {option.description && <span className="text-xs text-muted-foreground">{option.description}</span>}
                                </Label>
                            </div>
                        );
                    })}
                </RadioGroup>
            );

        case 'checkbox':
            return (
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={id}
                        aria-invalid={invalid}
                        aria-describedby={describedBy}
                        disabled={schema.disabled}
                        checked={value === true}
                        onCheckedChange={(next) => onChange(next === true)}
                    />
                    <Label htmlFor={id} className="text-sm font-normal">
                        {schema.placeholder ?? schema.label}
                    </Label>
                </div>
            );

        case 'switch':
            return (
                <Switch
                    id={id}
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                    disabled={schema.disabled}
                    checked={value === true}
                    onCheckedChange={onChange}
                />
            );

        case 'date':
            return (
                <DatePicker
                    value={value instanceof Date ? value : typeof value === 'string' && value ? new Date(value) : undefined}
                    onChange={(date) => onChange(date ? date.toISOString().slice(0, 10) : null)}
                    placeholder={schema.placeholder ?? 'Pick a date'}
                    disabled={schema.disabled}
                />
            );

        case 'time':
            return (
                <TimePicker
                    value={value instanceof Date ? value : typeof value === 'string' && value ? new Date(value) : undefined}
                    onChange={(date) => onChange(date.toISOString())}
                    hourCycle={schema.hourCycle ?? 24}
                    disabled={schema.disabled}
                />
            );

        case 'color':
            return <ColorPicker value={typeof value === 'string' ? value : '#000000'} onChange={onChange} disabled={schema.disabled} />;

        case 'file':
            return (
                <FileUpload
                    value={Array.isArray(value) ? (value as File[]) : value instanceof File ? [value] : []}
                    onChange={(files) => onChange(schema.multiple ? files : (files[0] ?? null))}
                    multiple={schema.multiple ?? false}
                    accept={schema.accept}
                    maxSizeMb={schema.maxSizeMb}
                    describedBy={describedBy}
                    invalid={invalid}
                />
            );

        case 'image':
            return (
                <ImageUpload
                    value={value instanceof File || typeof value === 'string' ? value : null}
                    onChange={onChange}
                    accept={schema.accept ?? 'image/*'}
                    maxSizeMb={schema.maxSizeMb}
                    label={schema.label}
                    invalid={invalid}
                    describedBy={describedBy}
                />
            );
    }
}

interface RepeaterProps {
    field: Extract<FormFieldSchema, { type: 'repeater' }>;
    name: string;
    values: FormValues;
}

function RepeaterControl({ field, name, values }: RepeaterProps) {
    const { control } = useFormContext();
    const { fields, append, remove } = useFieldArray({ control, name });

    const min = field.min ?? 0;
    const max = field.max ?? Infinity;

    function blankRow(): Record<string, unknown> {
        return Object.fromEntries(field.fields.filter((child) => child.type !== 'section').map((child) => [child.name, null]));
    }

    return (
        <fieldset className="col-span-full space-y-3">
            <legend className="text-sm font-medium">{field.label}</legend>
            {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}

            <ul className="space-y-3">
                {fields.map((row, index) => (
                    <li key={row.id} className="rounded-lg border border-border p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {field.label} {index + 1}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                disabled={fields.length <= min}
                                onClick={() => remove(index)}
                                aria-label={`Remove ${field.label} ${index + 1}`}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                            </Button>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {field.fields.map((child) => (
                                <FormFieldRenderer key={child.name} field={child} namePrefix={`${name}.${index}`} values={values} />
                            ))}
                        </div>
                    </li>
                ))}
            </ul>

            <Button type="button" variant="outline" size="sm" disabled={fields.length >= max} onClick={() => append(blankRow())}>
                <Plus className="size-4" aria-hidden="true" />
                {field.addLabel ?? `Add ${field.label.toLowerCase()}`}
            </Button>
        </fieldset>
    );
}
