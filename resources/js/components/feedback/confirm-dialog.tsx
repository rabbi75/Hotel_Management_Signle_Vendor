import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useEffect, useId, useState } from 'react';
import { useConfirmStore, type ConfirmOptions } from './use-confirm';

export interface ConfirmDialogProps extends ConfirmOptions {
    open: boolean;
    onResolve: (result: boolean) => void;
}

export function ConfirmDialog({
    open,
    onResolve,
    title,
    description,
    confirmLabel,
    cancelLabel = 'Cancel',
    variant = 'default',
    confirmWord,
}: ConfirmDialogProps) {
    const inputId = useId();
    const [typed, setTyped] = useState('');

    useEffect(() => {
        if (open) {
            setTyped('');
        }
    }, [open]);

    const destructive = variant === 'destructive';
    const locked = Boolean(confirmWord) && typed.trim().toLowerCase() !== (confirmWord ?? '').toLowerCase();

    return (
        <AlertDialog open={open} onOpenChange={(next) => (next ? undefined : onResolve(false))}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && <AlertDialogDescription>{description}</AlertDialogDescription>}
                </AlertDialogHeader>

                {confirmWord && (
                    <div className="grid gap-2">
                        <Label htmlFor={inputId}>
                            Type <span className="font-mono font-semibold text-foreground">{confirmWord}</span> to confirm
                        </Label>
                        <Input
                            id={inputId}
                            value={typed}
                            autoComplete="off"
                            autoFocus
                            aria-invalid={locked && typed.length > 0}
                            aria-describedby={`${inputId}-help`}
                            onChange={(event) => setTyped(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' && !locked) {
                                    event.preventDefault();
                                    onResolve(true);
                                }
                            }}
                        />
                        <p id={`${inputId}-help`} className="text-xs text-muted-foreground">
                            This action cannot be undone.
                        </p>
                    </div>
                )}

                <AlertDialogFooter>
                    <AlertDialogCancel onClick={() => onResolve(false)}>{cancelLabel}</AlertDialogCancel>
                    <AlertDialogAction
                        disabled={locked}
                        onClick={(event) => {
                            if (locked) {
                                event.preventDefault();

                                return;
                            }

                            onResolve(true);
                        }}
                        className={cn(buttonVariants({ variant: destructive ? 'destructive' : 'default' }))}
                    >
                        {confirmLabel ?? (destructive ? 'Delete' : 'Confirm')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** Mount once near the root; `useConfirm()` drives it from anywhere below. */
export function ConfirmDialogHost() {
    const request = useConfirmStore((state) => state.request);
    const settle = useConfirmStore((state) => state.settle);

    if (!request) {
        return null;
    }

    const { id, ...options } = request;

    return <ConfirmDialog key={id} open onResolve={(result) => settle(id, result)} {...options} />;
}
