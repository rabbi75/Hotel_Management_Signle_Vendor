import { cn } from '@/lib/utils';
import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, type ReactNode } from 'react';
import { FormProvider, useForm, useWatch, type DefaultValues, type FieldValues, type Resolver, type UseFormReturn } from 'react-hook-form';
import type { z } from 'zod';
import { FormActions } from './form-actions';
import { FormFieldRenderer } from './form-field';
import { fieldNames, isFieldVisible, type FormFieldSchema, type FormValues } from './schema';

export interface FormBuilderProps<TValues extends FieldValues> {
    schema: FormFieldSchema[];
    /** Client-side validation. Server messages still win once they arrive. */
    validation?: z.ZodType<TValues>;
    defaultValues: DefaultValues<TValues>;
    onSubmit: (values: TValues, form: UseFormReturn<TValues>) => void | Promise<void>;
    /** Inertia's `errors` bag, mapped onto the matching fields. */
    errors?: Record<string, string>;
    submitting?: boolean;
    saved?: boolean;
    submitLabel?: string;
    cancelLabel?: string;
    onCancel?: () => void;
    stickyActions?: boolean;
    /** Replaces the default action bar entirely. */
    actions?: (form: UseFormReturn<TValues>) => ReactNode;
    children?: (form: UseFormReturn<TValues>) => ReactNode;
    className?: string;
    id?: string;
}

/**
 * Renders a form from a field schema.
 *
 * Validation is layered: zod runs on the client for instant feedback, and the
 * server's `errors` bag is merged in on arrival — the server always has the
 * final say, since it is the only side that can check uniqueness or policy.
 */
export function FormBuilder<TValues extends FieldValues>({
    schema,
    validation,
    defaultValues,
    onSubmit,
    errors,
    submitting = false,
    saved = false,
    submitLabel,
    cancelLabel,
    onCancel,
    stickyActions = true,
    actions,
    children,
    className,
    id,
}: FormBuilderProps<TValues>) {
    const form = useForm<TValues>({
        defaultValues,
        mode: 'onBlur',
        ...(validation ? { resolver: zodResolver(validation as never) as unknown as Resolver<TValues> } : {}),
    });

    const { setError, reset, control, formState } = form;
    const values = useWatch({ control }) as FormValues;

    const known = fieldNames(schema);
    const errorSignature = JSON.stringify(errors ?? {});

    useEffect(() => {
        const bag: Record<string, string> = errors ?? {};

        for (const [field, message] of Object.entries(bag)) {
            // Laravel reports nested rules as `rows.0.name`; the root segment is
            // what the schema declares, so both shapes are matched.
            const root = field.split('.')[0] ?? field;

            if (known.includes(field) || known.includes(root)) {
                setError(field as never, { type: 'server', message });
            }
        }
        // `known` is derived from a prop that rarely changes; the signature keeps
        // this from re-running on every render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errorSignature, setError]);

    return (
        <FormProvider {...form}>
            <form
                id={id}
                noValidate
                className={cn('space-y-6', className)}
                onSubmit={(event) => {
                    event.preventDefault();
                    void form.handleSubmit((data) => onSubmit(data, form))(event);
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {schema
                        .filter((field) => isFieldVisible(field.showWhen, values))
                        .map((field) => (
                            <FormFieldRenderer key={field.name} field={field} values={values} />
                        ))}
                </div>

                {children?.(form)}

                {actions ? (
                    actions(form)
                ) : (
                    <FormActions
                        dirty={formState.isDirty}
                        submitting={submitting || formState.isSubmitting}
                        saved={saved}
                        sticky={stickyActions}
                        {...(submitLabel ? { submitLabel } : {})}
                        {...(cancelLabel ? { cancelLabel } : {})}
                        {...(onCancel ? { onCancel } : { onCancel: () => reset() })}
                    />
                )}
            </form>
        </FormProvider>
    );
}
