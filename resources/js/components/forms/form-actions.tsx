import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Check, CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

export interface FormActionsProps {
    dirty: boolean;
    submitting?: boolean;
    /** Shows the "Saved" confirmation after a successful submit. */
    saved?: boolean;
    submitLabel?: string;
    cancelLabel?: string;
    onCancel?: () => void;
    /** Sticks to the bottom of the viewport while the form is dirty. */
    sticky?: boolean;
    children?: ReactNode;
    className?: string;
}

export function FormActions({
    dirty,
    submitting = false,
    saved = false,
    submitLabel = 'Save changes',
    cancelLabel = 'Discard',
    onCancel,
    sticky = true,
    children,
    className,
}: FormActionsProps) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center gap-3 border-t border-border pt-4',
                sticky && 'glass sticky bottom-0 z-20 -mx-4 mt-6 px-4 pb-4 sm:-mx-6 sm:px-6',
                className,
            )}
        >
            <div className="flex min-w-0 flex-1 items-center gap-2 text-sm" aria-live="polite">
                {saved && !dirty && (
                    <span className="inline-flex items-center gap-1.5 text-success">
                        <Check className="size-4" aria-hidden="true" />
                        Saved
                    </span>
                )}
                {dirty && (
                    <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                        <CircleAlert className="size-4 text-warning" aria-hidden="true" />
                        Unsaved changes
                    </span>
                )}
            </div>

            {children}

            {onCancel && (
                <Button type="button" variant="ghost" onClick={onCancel} disabled={!dirty || submitting}>
                    {cancelLabel}
                </Button>
            )}
            <Button type="submit" loading={submitting} disabled={submitting || !dirty}>
                {submitLabel}
            </Button>
        </div>
    );
}
