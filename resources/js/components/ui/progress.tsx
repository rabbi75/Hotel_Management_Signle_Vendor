import { cn } from '@/lib/utils';
import { Progress as ProgressPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export function Progress({ className, value, ...props }: ComponentProps<typeof ProgressPrimitive.Root>) {
    return (
        <ProgressPrimitive.Root
            className={cn('relative h-2 w-full overflow-hidden rounded-full bg-secondary', className)}
            value={value}
            {...props}
        >
            <ProgressPrimitive.Indicator
                className="size-full flex-1 bg-primary transition-transform duration-300 ease-out-quint"
                style={{ transform: `translateX(-${100 - (value ?? 0)}%)` }}
            />
        </ProgressPrimitive.Root>
    );
}
