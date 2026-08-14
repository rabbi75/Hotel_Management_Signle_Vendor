import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import type { ComponentProps } from 'react';

export const spinnerVariants = cva('animate-spin text-muted-foreground', {
    variants: {
        size: {
            sm: 'size-3.5',
            default: 'size-5',
            lg: 'size-8',
        },
    },
    defaultVariants: {
        size: 'default',
    },
});

export interface SpinnerProps extends ComponentProps<'svg'>, VariantProps<typeof spinnerVariants> {}

export function Spinner({ className, size, ...props }: SpinnerProps) {
    return <Loader2 role="status" aria-label="Loading" className={cn(spinnerVariants({ size }), className)} {...props} />;
}
