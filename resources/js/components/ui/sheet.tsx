import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { X } from 'lucide-react';
import { Dialog as SheetPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export const Sheet = SheetPrimitive.Root;
export const SheetTrigger = SheetPrimitive.Trigger;
export const SheetPortal = SheetPrimitive.Portal;
export const SheetClose = SheetPrimitive.Close;

export function SheetOverlay({ className, ...props }: ComponentProps<typeof SheetPrimitive.Overlay>) {
    return (
        <SheetPrimitive.Overlay
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

export const sheetVariants = cva(
    'fixed z-50 flex flex-col gap-4 border-border bg-card shadow-lg outline-none data-[state=closed]:animate-out data-[state=closed]:duration-200 data-[state=open]:animate-in data-[state=open]:duration-300',
    {
        variants: {
            side: {
                top: 'inset-x-0 top-0 border-b data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top',
                bottom: 'inset-x-0 bottom-0 border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom',
                left: 'inset-y-0 left-0 h-full w-3/4 border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left sm:max-w-sm',
                right: 'inset-y-0 right-0 h-full w-3/4 border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right sm:max-w-sm',
            },
        },
        defaultVariants: {
            side: 'right',
        },
    },
);

export interface SheetContentProps extends ComponentProps<typeof SheetPrimitive.Content>, VariantProps<typeof sheetVariants> {
    showClose?: boolean;
}

export function SheetContent({ side = 'right', className, children, showClose = true, ...props }: SheetContentProps) {
    return (
        <SheetPortal>
            <SheetOverlay />
            <SheetPrimitive.Content className={cn(sheetVariants({ side }), className)} {...props}>
                {children}
                {showClose && (
                    <SheetPrimitive.Close className="absolute top-4 right-4 rounded-sm opacity-70 transition-opacity outline-none hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none">
                        <X className="size-4" />
                        <span className="sr-only">Close</span>
                    </SheetPrimitive.Close>
                )}
            </SheetPrimitive.Content>
        </SheetPortal>
    );
}

export function SheetHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex flex-col gap-1.5 p-6', className)} {...props} />;
}

export function SheetFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('mt-auto flex flex-col gap-2 p-6', className)} {...props} />;
}

export function SheetTitle({ className, ...props }: ComponentProps<typeof SheetPrimitive.Title>) {
    return <SheetPrimitive.Title className={cn('text-lg font-semibold text-foreground', className)} {...props} />;
}

export function SheetDescription({ className, ...props }: ComponentProps<typeof SheetPrimitive.Description>) {
    return <SheetPrimitive.Description className={cn('text-sm text-muted-foreground', className)} {...props} />;
}
