import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { Slot } from 'radix-ui';
import type { ComponentProps } from 'react';

export const badgeVariants = cva(
    'inline-flex w-fit shrink-0 items-center justify-center gap-1 rounded-md border border-transparent px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-colors [&_svg]:pointer-events-none [&_svg]:size-3',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground',
                secondary: 'bg-secondary text-secondary-foreground',
                outline: 'border-border text-foreground',
                success: 'bg-success text-success-foreground',
                warning: 'bg-warning text-warning-foreground',
                destructive: 'bg-destructive text-destructive-foreground',
                info: 'bg-info text-info-foreground',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

export interface BadgeProps extends ComponentProps<'span'>, VariantProps<typeof badgeVariants> {
    asChild?: boolean;
}

export function Badge({ className, variant, asChild = false, ...props }: BadgeProps) {
    const Comp = asChild ? Slot.Root : 'span';

    return <Comp className={cn(badgeVariants({ variant }), className)} {...props} />;
}
