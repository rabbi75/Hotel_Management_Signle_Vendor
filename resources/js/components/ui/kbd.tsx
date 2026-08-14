import { cn } from '@/lib/utils';
import type { ComponentProps } from 'react';

export function Kbd({ className, ...props }: ComponentProps<'kbd'>) {
    return (
        <kbd
            className={cn(
                'inline-flex h-5 min-w-5 items-center justify-center gap-1 rounded-sm border border-border bg-muted px-1.5 font-mono text-xs font-medium text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}
