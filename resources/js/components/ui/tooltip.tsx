import { cn } from '@/lib/utils';
import { Tooltip as TooltipPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export const TooltipProvider = TooltipPrimitive.Provider;
export const Tooltip = TooltipPrimitive.Root;
export const TooltipTrigger = TooltipPrimitive.Trigger;

export function TooltipContent({ className, sideOffset = 6, children, ...props }: ComponentProps<typeof TooltipPrimitive.Content>) {
    return (
        <TooltipPrimitive.Portal>
            <TooltipPrimitive.Content
                sideOffset={sideOffset}
                className={cn(
                    'z-50 w-fit origin-(--radix-tooltip-content-transform-origin) rounded-md bg-foreground px-3 py-1.5 text-xs text-balance text-background shadow-md',
                    'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    'data-[state=delayed-open]:animate-in data-[state=delayed-open]:fade-in-0 data-[state=delayed-open]:zoom-in-95',
                    'data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                    className,
                )}
                {...props}
            >
                {children}
                <TooltipPrimitive.Arrow className="z-50 fill-foreground" />
            </TooltipPrimitive.Content>
        </TooltipPrimitive.Portal>
    );
}
