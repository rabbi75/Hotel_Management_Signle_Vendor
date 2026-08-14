import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import { Slot } from 'radix-ui';
import type { ComponentProps } from 'react';

export const buttonVariants = cva(
    'inline-flex shrink-0 items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90',
                secondary: 'bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80',
                outline: 'border border-border bg-background shadow-xs hover:bg-accent hover:text-accent-foreground',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
                destructive: 'bg-destructive text-destructive-foreground shadow-xs hover:bg-destructive/90',
                success: 'bg-success text-success-foreground shadow-xs hover:bg-success/90',
            },
            size: {
                xs: 'h-7 gap-1.5 rounded-sm px-2 text-xs [&_svg]:size-3.5',
                sm: 'h-8 gap-1.5 px-3 [&_svg]:size-4',
                default: 'h-9 px-4 [&_svg]:size-4',
                lg: 'h-10 px-6 text-base [&_svg]:size-5',
                icon: 'size-9 [&_svg]:size-4',
                'icon-sm': 'size-8 [&_svg]:size-4',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export interface ButtonProps extends ComponentProps<'button'>, VariantProps<typeof buttonVariants> {
    asChild?: boolean;
    loading?: boolean;
}

export function Button({ className, variant, size, asChild = false, loading = false, disabled, children, ...props }: ButtonProps) {
    if (asChild) {
        return (
            <Slot.Root className={cn(buttonVariants({ variant, size }), className)} {...props}>
                {children}
            </Slot.Root>
        );
    }

    return (
        <button
            className={cn(buttonVariants({ variant, size }), className)}
            disabled={disabled ?? loading}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading && <Loader2 className="animate-spin" aria-hidden="true" />}
            {children}
        </button>
    );
}
