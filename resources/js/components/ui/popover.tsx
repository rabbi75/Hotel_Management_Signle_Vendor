import { cn } from '@/lib/utils';
import { Popover as PopoverPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export const Popover = PopoverPrimitive.Root;
export const PopoverTrigger = PopoverPrimitive.Trigger;
export const PopoverAnchor = PopoverPrimitive.Anchor;
export const PopoverClose = PopoverPrimitive.Close;

export function PopoverContent({ className, align = 'center', sideOffset = 8, ...props }: ComponentProps<typeof PopoverPrimitive.Content>) {
    return (
        <PopoverPrimitive.Portal>
            <PopoverPrimitive.Content
                align={align}
                sideOffset={sideOffset}
                className={cn(
                    'z-50 w-72 origin-(--radix-popover-content-transform-origin) rounded-lg border border-border bg-popover p-4 text-popover-foreground shadow-md outline-none',
                    'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    'data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                    className,
                )}
                {...props}
            />
        </PopoverPrimitive.Portal>
    );
}
