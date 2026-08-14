import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { Command as CommandPrimitive } from 'cmdk';
import { Search } from 'lucide-react';
import type { ComponentProps } from 'react';

export function Command({ className, ...props }: ComponentProps<typeof CommandPrimitive>) {
    return (
        <CommandPrimitive
            className={cn('flex size-full flex-col overflow-hidden rounded-md bg-popover text-popover-foreground', className)}
            {...props}
        />
    );
}

export interface CommandDialogProps extends ComponentProps<typeof Dialog> {
    title?: string;
    description?: string;
    className?: string;
}

export function CommandDialog({
    title = 'Command palette',
    description = 'Search for a command to run',
    children,
    className,
    ...props
}: CommandDialogProps) {
    return (
        <Dialog {...props}>
            <DialogHeader className="sr-only">
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
            </DialogHeader>
            <DialogContent className={cn('overflow-hidden p-0', className)} showClose={false}>
                <Command className="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground [&_[cmdk-group]]:px-2 [&_[cmdk-group]:not([hidden])_~[cmdk-group]]:pt-0 [&_[cmdk-input-wrapper]_svg]:size-5 [&_[cmdk-input]]:h-12 [&_[cmdk-item]]:px-2 [&_[cmdk-item]]:py-3 [&_[cmdk-item]_svg]:size-5">
                    {children}
                </Command>
            </DialogContent>
        </Dialog>
    );
}

export function CommandInput({ className, ...props }: ComponentProps<typeof CommandPrimitive.Input>) {
    return (
        // eslint-disable-next-line react/no-unknown-property -- cmdk styles this wrapper via the bare `cmdk-input-wrapper` attribute selector
        <div className="flex items-center gap-2 border-b border-border px-3" cmdk-input-wrapper="">
            <Search className="size-4 shrink-0 opacity-50" />
            <CommandPrimitive.Input
                className={cn(
                    'flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
                    className,
                )}
                {...props}
            />
        </div>
    );
}

export function CommandList({ className, ...props }: ComponentProps<typeof CommandPrimitive.List>) {
    return <CommandPrimitive.List className={cn('max-h-80 overflow-x-hidden overflow-y-auto', className)} {...props} />;
}

export function CommandEmpty({ ...props }: ComponentProps<typeof CommandPrimitive.Empty>) {
    return <CommandPrimitive.Empty className="py-6 text-center text-sm text-muted-foreground" {...props} />;
}

export function CommandGroup({ className, ...props }: ComponentProps<typeof CommandPrimitive.Group>) {
    return (
        <CommandPrimitive.Group
            className={cn(
                'overflow-hidden p-1 text-foreground [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}

export function CommandSeparator({ className, ...props }: ComponentProps<typeof CommandPrimitive.Separator>) {
    return <CommandPrimitive.Separator className={cn('-mx-1 h-px bg-border', className)} {...props} />;
}

export function CommandItem({ className, ...props }: ComponentProps<typeof CommandPrimitive.Item>) {
    return (
        <CommandPrimitive.Item
            className={cn(
                'relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
                'data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground',
                className,
            )}
            {...props}
        />
    );
}

export function CommandShortcut({ className, ...props }: ComponentProps<'span'>) {
    return <span className={cn('ml-auto text-xs tracking-widest text-muted-foreground', className)} {...props} />;
}
