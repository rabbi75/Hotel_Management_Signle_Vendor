import { cn } from '@/lib/utils';
import { Check, ChevronRight, Circle } from 'lucide-react';
import { ContextMenu as ContextMenuPrimitive } from 'radix-ui';
import type { ComponentProps, HTMLAttributes } from 'react';

export const ContextMenu = ContextMenuPrimitive.Root;
export const ContextMenuTrigger = ContextMenuPrimitive.Trigger;
export const ContextMenuGroup = ContextMenuPrimitive.Group;
export const ContextMenuPortal = ContextMenuPrimitive.Portal;
export const ContextMenuSub = ContextMenuPrimitive.Sub;
export const ContextMenuRadioGroup = ContextMenuPrimitive.RadioGroup;

export function ContextMenuContent({ className, ...props }: ComponentProps<typeof ContextMenuPrimitive.Content>) {
    return (
        <ContextMenuPrimitive.Portal>
            <ContextMenuPrimitive.Content
                className={cn(
                    'z-50 min-w-40 origin-(--radix-context-menu-content-transform-origin) overflow-hidden rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md',
                    'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    className,
                )}
                {...props}
            />
        </ContextMenuPrimitive.Portal>
    );
}

const menuItemClasses = cn(
    'relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    'focus:bg-accent focus:text-accent-foreground',
);

export interface ContextMenuItemProps extends ComponentProps<typeof ContextMenuPrimitive.Item> {
    variant?: 'default' | 'destructive';
    inset?: boolean;
}

export function ContextMenuItem({ className, variant = 'default', inset, ...props }: ContextMenuItemProps) {
    return (
        <ContextMenuPrimitive.Item
            className={cn(
                menuItemClasses,
                inset && 'pl-8',
                variant === 'destructive' && 'text-destructive focus:bg-destructive/10 focus:text-destructive [&_svg]:text-destructive',
                className,
            )}
            {...props}
        />
    );
}

export function ContextMenuCheckboxItem({
    className,
    children,
    checked,
    ...props
}: ComponentProps<typeof ContextMenuPrimitive.CheckboxItem>) {
    return (
        <ContextMenuPrimitive.CheckboxItem className={cn(menuItemClasses, 'py-1.5 pr-2 pl-8', className)} checked={checked} {...props}>
            <span className="absolute left-2 flex size-3.5 items-center justify-center">
                <ContextMenuPrimitive.ItemIndicator>
                    <Check className="size-4" />
                </ContextMenuPrimitive.ItemIndicator>
            </span>
            {children}
        </ContextMenuPrimitive.CheckboxItem>
    );
}

export function ContextMenuRadioItem({ className, children, ...props }: ComponentProps<typeof ContextMenuPrimitive.RadioItem>) {
    return (
        <ContextMenuPrimitive.RadioItem className={cn(menuItemClasses, 'py-1.5 pr-2 pl-8', className)} {...props}>
            <span className="absolute left-2 flex size-3.5 items-center justify-center">
                <ContextMenuPrimitive.ItemIndicator>
                    <Circle className="size-2 fill-current" />
                </ContextMenuPrimitive.ItemIndicator>
            </span>
            {children}
        </ContextMenuPrimitive.RadioItem>
    );
}

export function ContextMenuLabel({ className, inset, ...props }: ComponentProps<typeof ContextMenuPrimitive.Label> & { inset?: boolean }) {
    return (
        <ContextMenuPrimitive.Label
            className={cn('px-2 py-1.5 text-xs font-medium text-muted-foreground', inset && 'pl-8', className)}
            {...props}
        />
    );
}

export function ContextMenuSeparator({ className, ...props }: ComponentProps<typeof ContextMenuPrimitive.Separator>) {
    return <ContextMenuPrimitive.Separator className={cn('-mx-1 my-1 h-px bg-border', className)} {...props} />;
}

export function ContextMenuShortcut({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
    return <span className={cn('ml-auto text-xs tracking-widest text-muted-foreground', className)} {...props} />;
}

export function ContextMenuSubTrigger({
    className,
    inset,
    children,
    ...props
}: ComponentProps<typeof ContextMenuPrimitive.SubTrigger> & { inset?: boolean }) {
    return (
        <ContextMenuPrimitive.SubTrigger
            className={cn(
                menuItemClasses,
                'data-[state=open]:bg-accent data-[state=open]:text-accent-foreground',
                inset && 'pl-8',
                className,
            )}
            {...props}
        >
            {children}
            <ChevronRight className="ml-auto size-4" />
        </ContextMenuPrimitive.SubTrigger>
    );
}

export function ContextMenuSubContent({ className, ...props }: ComponentProps<typeof ContextMenuPrimitive.SubContent>) {
    return (
        <ContextMenuPrimitive.SubContent
            className={cn(
                'z-50 min-w-32 origin-(--radix-context-menu-content-transform-origin) overflow-hidden rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-lg',
                'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                className,
            )}
            {...props}
        />
    );
}
