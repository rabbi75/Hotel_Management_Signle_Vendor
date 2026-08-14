import { cn } from '@/lib/utils';
import { ScrollArea as ScrollAreaPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export function ScrollArea({ className, children, ...props }: ComponentProps<typeof ScrollAreaPrimitive.Root>) {
    return (
        <ScrollAreaPrimitive.Root className={cn('relative overflow-hidden', className)} {...props}>
            <ScrollAreaPrimitive.Viewport className="size-full rounded-[inherit] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">
                {children}
            </ScrollAreaPrimitive.Viewport>
            <ScrollBar />
            <ScrollAreaPrimitive.Corner />
        </ScrollAreaPrimitive.Root>
    );
}

export function ScrollBar({ className, orientation = 'vertical', ...props }: ComponentProps<typeof ScrollAreaPrimitive.Scrollbar>) {
    return (
        <ScrollAreaPrimitive.Scrollbar
            orientation={orientation}
            className={cn(
                'flex touch-none p-px transition-colors select-none',
                orientation === 'vertical' && 'h-full w-2.5 border-l border-l-transparent',
                orientation === 'horizontal' && 'h-2.5 flex-col border-t border-t-transparent',
                className,
            )}
            {...props}
        >
            <ScrollAreaPrimitive.Thumb className="relative flex-1 rounded-full bg-border" />
        </ScrollAreaPrimitive.Scrollbar>
    );
}
