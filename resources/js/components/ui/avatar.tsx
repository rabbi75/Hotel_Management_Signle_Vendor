import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { Avatar as AvatarPrimitive } from 'radix-ui';
import { Children, type ComponentProps, isValidElement, type ReactNode } from 'react';

export const avatarVariants = cva('relative flex shrink-0 overflow-hidden rounded-full', {
    variants: {
        size: {
            xs: 'size-6',
            sm: 'size-8',
            default: 'size-10',
            lg: 'size-12',
            xl: 'size-16',
        },
    },
    defaultVariants: {
        size: 'default',
    },
});

export interface AvatarProps extends ComponentProps<typeof AvatarPrimitive.Root>, VariantProps<typeof avatarVariants> {}

export function Avatar({ className, size, ...props }: AvatarProps) {
    return <AvatarPrimitive.Root className={cn(avatarVariants({ size }), className)} {...props} />;
}

export function AvatarImage({ className, ...props }: ComponentProps<typeof AvatarPrimitive.Image>) {
    return <AvatarPrimitive.Image className={cn('aspect-square size-full object-cover', className)} {...props} />;
}

export function AvatarFallback({ className, ...props }: ComponentProps<typeof AvatarPrimitive.Fallback>) {
    return (
        <AvatarPrimitive.Fallback
            className={cn(
                'flex size-full items-center justify-center rounded-full bg-muted text-sm font-medium text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}

export interface AvatarGroupProps extends ComponentProps<'div'>, VariantProps<typeof avatarVariants> {
    max?: number;
    children: ReactNode;
}

/** Stacks avatar children with a negative-margin overlap and folds anything past `max` into a "+N" chip. */
export function AvatarGroup({ className, size, max = 4, children, ...props }: AvatarGroupProps) {
    const items = Children.toArray(children).filter(isValidElement);
    const visible = items.slice(0, max);
    const overflow = items.length - visible.length;

    return (
        <div className={cn('flex items-center -space-x-2', className)} {...props}>
            {visible.map((child, index) => (
                <div key={index} className="relative rounded-full ring-2 ring-background">
                    {child}
                </div>
            ))}
            {overflow > 0 && (
                <div className={cn(avatarVariants({ size }), 'ring-2 ring-background')}>
                    <div className="flex size-full items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground">
                        +{overflow}
                    </div>
                </div>
            )}
        </div>
    );
}
