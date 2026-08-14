import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { Slot } from 'radix-ui';
import { type ComponentProps, createContext, use, useId, useMemo } from 'react';
import {
    Controller,
    type ControllerProps,
    type FieldPath,
    type FieldValues,
    FormProvider,
    useFormContext,
    useFormState,
} from 'react-hook-form';

export const Form = FormProvider;

interface FormFieldContextValue<
    TFieldValues extends FieldValues = FieldValues,
    TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>,
> {
    name: TName;
}

const FormFieldContext = createContext<FormFieldContextValue | null>(null);

export function FormField<TFieldValues extends FieldValues = FieldValues, TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>>(
    props: ControllerProps<TFieldValues, TName>,
) {
    return (
        <FormFieldContext value={{ name: props.name }}>
            <Controller {...props} />
        </FormFieldContext>
    );
}

interface FormItemContextValue {
    id: string;
}

const FormItemContext = createContext<FormItemContextValue | null>(null);

function useFormField() {
    const fieldContext = use(FormFieldContext);
    const itemContext = use(FormItemContext);
    const { getFieldState } = useFormContext();
    const formState = useFormState({ name: fieldContext?.name });

    if (!fieldContext || !itemContext) {
        throw new Error('useFormField must be used within a <FormField> and <FormItem>');
    }

    const fieldState = getFieldState(fieldContext.name, formState);
    const { id } = itemContext;

    return {
        id,
        name: fieldContext.name,
        formItemId: `${id}-form-item`,
        formDescriptionId: `${id}-form-item-description`,
        formMessageId: `${id}-form-item-message`,
        ...fieldState,
    };
}

export function FormItem({ className, ...props }: ComponentProps<'div'>) {
    const id = useId();
    const value = useMemo(() => ({ id }), [id]);

    return (
        <FormItemContext value={value}>
            <div className={cn('grid gap-2', className)} {...props} />
        </FormItemContext>
    );
}

export function FormLabel({ className, ...props }: ComponentProps<typeof Label>) {
    const { error, formItemId } = useFormField();

    return <Label data-error={!!error} className={cn('data-[error=true]:text-destructive', className)} htmlFor={formItemId} {...props} />;
}

export function FormControl({ ...props }: ComponentProps<typeof Slot.Root>) {
    const { error, formItemId, formDescriptionId, formMessageId } = useFormField();

    return (
        <Slot.Root
            id={formItemId}
            aria-describedby={!error ? formDescriptionId : `${formDescriptionId} ${formMessageId}`}
            aria-invalid={!!error}
            {...props}
        />
    );
}

export function FormDescription({ className, ...props }: ComponentProps<'p'>) {
    const { formDescriptionId } = useFormField();

    return <p id={formDescriptionId} className={cn('text-sm text-muted-foreground', className)} {...props} />;
}

export function FormMessage({ className, children, ...props }: ComponentProps<'p'>) {
    const { error, formMessageId } = useFormField();
    const body = error ? String(error.message ?? '') : children;

    if (!body) {
        return null;
    }

    return (
        <p id={formMessageId} className={cn('text-sm font-medium text-destructive', className)} {...props}>
            {body}
        </p>
    );
}
