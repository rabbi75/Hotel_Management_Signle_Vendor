import { cn } from '@/lib/utils';
import { X } from 'lucide-react';
import { Dialog as DialogPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export const Dialog = DialogPrimitive.Root;
export const DialogTrigger = DialogPrimitive.Trigger;
export const DialogPortal = DialogPrimitive.Portal;
export const DialogClose = DialogPrimitive.Close;

export function DialogOverlay({ className, ...props }: ComponentProps<typeof DialogPrimitive.Overlay>) {
    return (
        <DialogPrimitive.Overlay
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

export interface DialogContentProps extends ComponentProps<typeof DialogPrimitive.Content> {
    showClose?: boolean;
}

export function DialogContent({ className, children, showClose = true, ...props }: DialogContentProps) {
    return (
        <DialogPortal>
            <DialogOverlay />
            <DialogPrimitive.Content
                className={cn(
                    'fixed top-1/2 left-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 rounded-xl border border-border bg-card p-6 shadow-lg outline-none',
                    'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    className,
                )}
                {...props}
            >
                {children}
                {showClose && (
                    <DialogPrimitive.Close className="absolute top-4 right-4 rounded-sm opacity-70 transition-opacity outline-none hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none">
                        <X className="size-4" />
                        <span className="sr-only">Close</span>
                    </DialogPrimitive.Close>
                )}
            </DialogPrimitive.Content>
        </DialogPortal>
    );
}

export function DialogHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex flex-col gap-1.5 text-center sm:text-left', className)} {...props} />;
}

export function DialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', className)} {...props} />;
}

export function DialogTitle({ className, ...props }: ComponentProps<typeof DialogPrimitive.Title>) {
    return <DialogPrimitive.Title className={cn('text-lg leading-none font-semibold', className)} {...props} />;
}

export function DialogDescription({ className, ...props }: ComponentProps<typeof DialogPrimitive.Description>) {
    return <DialogPrimitive.Description className={cn('text-sm text-muted-foreground', className)} {...props} />;
}
