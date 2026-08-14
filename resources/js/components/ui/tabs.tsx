import { cn } from '@/lib/utils';
import { Tabs as TabsPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export function Tabs({ className, ...props }: ComponentProps<typeof TabsPrimitive.Root>) {
    return <TabsPrimitive.Root className={cn('flex flex-col gap-2', className)} {...props} />;
}

export function TabsList({ className, ...props }: ComponentProps<typeof TabsPrimitive.List>) {
    return (
        <TabsPrimitive.List
            className={cn(
                'inline-flex h-9 w-fit items-center justify-center gap-1 rounded-lg bg-muted p-1 text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}

export function TabsTrigger({ className, ...props }: ComponentProps<typeof TabsPrimitive.Trigger>) {
    return (
        <TabsPrimitive.Trigger
            className={cn(
                'inline-flex h-7 flex-1 items-center justify-center gap-1.5 rounded-md px-3 text-sm font-medium whitespace-nowrap text-foreground transition-[color,box-shadow] outline-none',
                'data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm',
                'data-[state=inactive]:text-muted-foreground',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                'disabled:pointer-events-none disabled:opacity-50',
                '[&_svg]:size-4 [&_svg]:shrink-0',
                className,
            )}
            {...props}
        />
    );
}

export function TabsContent({ className, ...props }: ComponentProps<typeof TabsPrimitive.Content>) {
    return (
        <TabsPrimitive.Content
            className={cn('flex-1 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2', className)}
            {...props}
        />
    );
}
