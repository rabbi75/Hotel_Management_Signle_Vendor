import { cn } from '@/lib/utils';
import { Circle } from 'lucide-react';
import { RadioGroup as RadioGroupPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export function RadioGroup({ className, ...props }: ComponentProps<typeof RadioGroupPrimitive.Root>) {
    return <RadioGroupPrimitive.Root className={cn('grid gap-2', className)} {...props} />;
}

export function RadioGroupItem({ className, ...props }: ComponentProps<typeof RadioGroupPrimitive.Item>) {
    return (
        <RadioGroupPrimitive.Item
            className={cn(
                'aspect-square size-4 shrink-0 rounded-full border border-input text-primary shadow-xs transition-colors outline-none',
                'data-[state=checked]:border-primary',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <RadioGroupPrimitive.Indicator className="flex items-center justify-center">
                <Circle className="size-2 fill-current text-current" />
            </RadioGroupPrimitive.Indicator>
        </RadioGroupPrimitive.Item>
    );
}
