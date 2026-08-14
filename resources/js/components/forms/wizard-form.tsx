import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { useMemo, useState } from 'react';
import { FormProvider, useForm, useWatch, type DefaultValues, type FieldValues, type Resolver, type UseFormReturn } from 'react-hook-form';
import type { z } from 'zod';
import { FormFieldRenderer } from './form-field';
import { fieldNames, isFieldVisible, type FormFieldSchema, type FormValues } from './schema';

export interface WizardStep {
    id: string;
    title: string;
    description?: string;
    fields: FormFieldSchema[];
}

export interface WizardFormProps<TValues extends FieldValues> {
    steps: WizardStep[];
    validation?: z.ZodType<TValues>;
    defaultValues: DefaultValues<TValues>;
    onSubmit: (values: TValues, form: UseFormReturn<TValues>) => void | Promise<void>;
    submitting?: boolean;
    finishLabel?: string;
    className?: string;
}

/**
 * A multi-step form that validates only the current step before advancing, so
 * a user is never blocked by a field they have not reached yet.
 */
export function WizardForm<TValues extends FieldValues>({
    steps,
    validation,
    defaultValues,
    onSubmit,
    submitting = false,
    finishLabel = 'Finish',
    className,
}: WizardFormProps<TValues>) {
    const form = useForm<TValues>({
        defaultValues,
        mode: 'onBlur',
        ...(validation ? { resolver: zodResolver(validation as never) as unknown as Resolver<TValues> } : {}),
    });

    const [index, setIndex] = useState(0);
    const [furthest, setFurthest] = useState(0);
    const values = useWatch({ control: form.control }) as FormValues;

    const step = steps[index];
    const isLast = index === steps.length - 1;

    const stepFields = useMemo(() => (step ? fieldNames(step.fields) : []), [step]);

    async function next(): Promise<void> {
        const valid = await form.trigger(stepFields as never);

        if (!valid) {
            return;
        }

        const target = Math.min(index + 1, steps.length - 1);
        setIndex(target);
        setFurthest((current) => Math.max(current, target));
    }

    if (!step) {
        return null;
    }

    return (
        <FormProvider {...form}>
            <form
                noValidate
                className={cn('space-y-6', className)}
                onSubmit={(event) => {
                    event.preventDefault();

                    if (!isLast) {
                        void next();

                        return;
                    }

                    void form.handleSubmit((data) => onSubmit(data, form))(event);
                }}
            >
                <div className="space-y-3">
                    <ol className="flex flex-wrap items-center gap-x-2 gap-y-1" aria-label="Progress">
                        {steps.map((entry, entryIndex) => {
                            const done = entryIndex < index;
                            const current = entryIndex === index;

                            return (
                                <li key={entry.id} className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        disabled={entryIndex > furthest}
                                        onClick={() => setIndex(entryIndex)}
                                        aria-current={current ? 'step' : undefined}
                                        className={cn(
                                            'flex items-center gap-2 rounded-md px-2 py-1 text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                                            current ? 'font-medium text-foreground' : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-medium',
                                                done && 'border-primary bg-primary text-primary-foreground',
                                                current && 'border-primary text-primary',
                                                !done && !current && 'border-border',
                                            )}
                                        >
                                            {done ? <Check className="size-3.5" aria-hidden="true" /> : entryIndex + 1}
                                        </span>
                                        <span className="hidden sm:inline">{entry.title}</span>
                                    </button>
                                    {entryIndex < steps.length - 1 && (
                                        <span className="hidden h-px w-6 bg-border sm:block" aria-hidden="true" />
                                    )}
                                </li>
                            );
                        })}
                    </ol>

                    <Progress value={((index + 1) / steps.length) * 100} className="h-1 sm:hidden" aria-label="Progress" />
                </div>

                <section aria-labelledby={`wizard-step-${step.id}`} className="space-y-4">
                    <div className="space-y-1">
                        <h3 id={`wizard-step-${step.id}`} className="text-base font-semibold">
                            {step.title}
                        </h3>
                        {step.description && <p className="text-sm text-muted-foreground">{step.description}</p>}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {step.fields
                            .filter((field) => isFieldVisible(field.showWhen, values))
                            .map((field) => (
                                <FormFieldRenderer key={field.name} field={field} values={values} />
                            ))}
                    </div>
                </section>

                <div className="flex items-center justify-between gap-3 border-t border-border pt-4">
                    <Button type="button" variant="ghost" disabled={index === 0 || submitting} onClick={() => setIndex(index - 1)}>
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back
                    </Button>

                    <p className="text-sm text-muted-foreground" aria-live="polite">
                        Step {index + 1} of {steps.length}
                    </p>

                    <Button type="submit" loading={submitting}>
                        {isLast ? finishLabel : 'Next'}
                        {!isLast && <ArrowRight className="size-4" aria-hidden="true" />}
                    </Button>
                </div>
            </form>
        </FormProvider>
    );
}
