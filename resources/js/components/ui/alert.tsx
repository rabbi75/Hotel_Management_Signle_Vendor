import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

export const alertVariants = cva(
    'relative grid w-full grid-cols-[0_1fr] items-start gap-y-0.5 rounded-lg border px-4 py-3 text-sm has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5',
    {
        variants: {
            variant: {
                default: 'border-border bg-card text-card-foreground [&>svg]:text-foreground',
                success: 'border-success/30 bg-success/10 text-success [&>svg]:text-success',
                warning: 'border-warning/30 bg-warning/10 text-warning [&>svg]:text-warning',
                destructive: 'border-destructive/30 bg-destructive/10 text-destructive [&>svg]:text-destructive',
                info: 'border-info/30 bg-info/10 text-info [&>svg]:text-info',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

export interface AlertProps extends ComponentProps<'div'>, VariantProps<typeof alertVariants> {}

export function Alert({ className, variant, ...props }: AlertProps) {
    return <div role="alert" className={cn(alertVariants({ variant }), className)} {...props} />;
}

export function AlertTitle({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('col-start-2 min-h-4 font-medium tracking-tight', className)} {...props} />;
}

export function AlertDescription({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('col-start-2 text-sm text-current/80 [&_p]:leading-relaxed', className)} {...props} />;
}
