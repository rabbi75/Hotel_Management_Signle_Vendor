import type { SelectOption } from '@/types';

export type FormValues = Record<string, unknown>;

/**
 * A field is shown when its condition passes. The declarative form covers the
 * common "depends on one other field" case without a closure, which keeps a
 * schema serialisable; the predicate form is the escape hatch.
 */
export type ShowWhen =
    | ((values: FormValues) => boolean)
    | {
          field: string;
          equals?: unknown;
          oneOf?: unknown[];
          truthy?: boolean;
      };

export interface BaseField {
    name: string;
    label: string;
    description?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    /** Grid span within the form's two-column layout. */
    span?: 1 | 2;
    showWhen?: ShowWhen;
}

export interface TextField extends BaseField {
    type: 'text' | 'email' | 'password';
    autoComplete?: string;
}

export interface NumberField extends BaseField {
    type: 'number';
    min?: number;
    max?: number;
    step?: number;
}

export interface TextareaField extends BaseField {
    type: 'textarea';
    rows?: number;
}

export interface OptionField extends BaseField {
    type: 'select' | 'multiselect' | 'radio';
    options: SelectOption[];
}

export interface ToggleField extends BaseField {
    type: 'checkbox' | 'switch';
}

export interface DateField extends BaseField {
    type: 'date';
}

export interface TimeField extends BaseField {
    type: 'time';
    hourCycle?: 12 | 24;
}

export interface ColorField extends BaseField {
    type: 'color';
}

export interface FileField extends BaseField {
    type: 'file';
    accept?: string;
    multiple?: boolean;
    maxSizeMb?: number;
}

export interface ImageField extends BaseField {
    type: 'image';
    accept?: string;
    maxSizeMb?: number;
}

export interface RepeaterField extends BaseField {
    type: 'repeater';
    fields: FormFieldSchema[];
    addLabel?: string;
    min?: number;
    max?: number;
}

export interface SectionField {
    type: 'section';
    name: string;
    label: string;
    description?: string;
    fields: FormFieldSchema[];
    showWhen?: ShowWhen;
}

export type FormFieldSchema =
    | TextField
    | NumberField
    | TextareaField
    | OptionField
    | ToggleField
    | DateField
    | TimeField
    | ColorField
    | FileField
    | ImageField
    | RepeaterField
    | SectionField;

function readPath(values: FormValues, path: string): unknown {
    return path.split('.').reduce<unknown>((carry, segment) => {
        if (carry === null || carry === undefined || typeof carry !== 'object') {
            return undefined;
        }

        return (carry as Record<string, unknown>)[segment];
    }, values);
}

export function isFieldVisible(condition: ShowWhen | undefined, values: FormValues): boolean {
    if (!condition) {
        return true;
    }

    if (typeof condition === 'function') {
        return condition(values);
    }

    const value = readPath(values, condition.field);

    if (condition.truthy !== undefined) {
        return condition.truthy ? Boolean(value) : !value;
    }

    if (condition.oneOf) {
        return condition.oneOf.includes(value);
    }

    if ('equals' in condition) {
        return value === condition.equals;
    }

    return Boolean(value);
}

/** Every leaf field name a schema declares, including those nested in sections. */
export function fieldNames(schema: FormFieldSchema[]): string[] {
    return schema.flatMap((field) => (field.type === 'section' ? fieldNames(field.fields) : [field.name]));
}
