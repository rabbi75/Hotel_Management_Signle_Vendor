import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { Toggle as TogglePrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export const toggleVariants = cva(
    'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap transition-colors outline-none hover:bg-muted hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=on]:bg-accent data-[state=on]:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-transparent',
                outline: 'border border-input bg-transparent shadow-xs hover:bg-accent hover:text-accent-foreground',
            },
            size: {
                sm: 'h-8 min-w-8 px-1.5 [&_svg]:size-3.5',
                default: 'h-9 min-w-9 px-2 [&_svg]:size-4',
                lg: 'h-10 min-w-10 px-2.5 [&_svg]:size-4',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export interface ToggleProps extends ComponentProps<typeof TogglePrimitive.Root>, VariantProps<typeof toggleVariants> {}

export function Toggle({ className, variant, size, ...props }: ToggleProps) {
    return <TogglePrimitive.Root className={cn(toggleVariants({ variant, size }), className)} {...props} />;
}
