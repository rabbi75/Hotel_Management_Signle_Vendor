import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { AlertDialog as AlertDialogPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export const AlertDialog = AlertDialogPrimitive.Root;
export const AlertDialogTrigger = AlertDialogPrimitive.Trigger;
export const AlertDialogPortal = AlertDialogPrimitive.Portal;

export function AlertDialogOverlay({ className, ...props }: ComponentProps<typeof AlertDialogPrimitive.Overlay>) {
    return (
        <AlertDialogPrimitive.Overlay
            className={cn(
                'fixed inset-0 z-50 bg-black/50',
                'data-[state=closed]:animate-out data-[state=closed]:fade-out-0',
                'data-[state=open]:animate-in data-[state=open]:fade-in-0',
                className,
            )}
            {...props}
        />
    );
}

export function AlertDialogContent({ className, ...props }: ComponentProps<typeof AlertDialogPrimitive.Content>) {
    return (
        <AlertDialogPortal>
            <AlertDialogOverlay />
            <AlertDialogPrimitive.Content
                className={cn(
                    'fixed top-1/2 left-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 rounded-xl border border-border bg-card p-6 shadow-lg outline-none',
                    'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    className,
                )}
                {...props}
            />
        </AlertDialogPortal>
    );
}

export function AlertDialogHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex flex-col gap-1.5 text-center sm:text-left', className)} {...props} />;
}

export function AlertDialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', className)} {...props} />;
}

export function AlertDialogTitle({ className, ...props }: ComponentProps<typeof AlertDialogPrimitive.Title>) {
    return <AlertDialogPrimitive.Title className={cn('text-lg leading-none font-semibold', className)} {...props} />;
}

export function AlertDialogDescription({ className, ...props }: ComponentProps<typeof AlertDialogPrimitive.Description>) {
    return <AlertDialogPrimitive.Description className={cn('text-sm text-muted-foreground', className)} {...props} />;
}

export function AlertDialogAction({ className, ...props }: ComponentProps<typeof AlertDialogPrimitive.Action>) {
    return <AlertDialogPrimitive.Action className={cn(buttonVariants(), className)} {...props} />;
}

export function AlertDialogCancel({ className, ...props }: ComponentProps<typeof AlertDialogPrimitive.Cancel>) {
    return <AlertDialogPrimitive.Cancel className={cn(buttonVariants({ variant: 'outline' }), className)} {...props} />;
}
