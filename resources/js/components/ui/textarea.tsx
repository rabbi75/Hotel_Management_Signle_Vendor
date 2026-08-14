import { cn } from '@/lib/utils';
import type { ComponentProps } from 'react';

export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                'placeholder:text-muted-foreground',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                'field-sizing-content',
                className,
            )}
            {...props}
        />
    );
}
