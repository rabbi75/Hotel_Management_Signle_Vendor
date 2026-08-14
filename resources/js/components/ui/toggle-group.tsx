import { toggleVariants, type ToggleProps } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';
import { ToggleGroup as ToggleGroupPrimitive } from 'radix-ui';
import { createContext, use, type ComponentProps } from 'react';

type ToggleGroupVariantProps = Pick<ToggleProps, 'variant' | 'size'>;

const ToggleGroupContext = createContext<ToggleGroupVariantProps>({ variant: 'default', size: 'default' });

export type ToggleGroupProps = ComponentProps<typeof ToggleGroupPrimitive.Root> & ToggleGroupVariantProps;

export function ToggleGroup({ className, variant, size, children, ...props }: ToggleGroupProps) {
    return (
        <ToggleGroupPrimitive.Root className={cn('flex w-fit items-center gap-1', className)} {...props}>
            <ToggleGroupContext value={{ variant, size }}>{children}</ToggleGroupContext>
        </ToggleGroupPrimitive.Root>
    );
}

export type ToggleGroupItemProps = ComponentProps<typeof ToggleGroupPrimitive.Item> & ToggleGroupVariantProps;

export function ToggleGroupItem({ className, children, variant, size, ...props }: ToggleGroupItemProps) {
    const context = use(ToggleGroupContext);

    return (
        <ToggleGroupPrimitive.Item
            className={cn(
                toggleVariants({
                    variant: context.variant ?? variant,
                    size: context.size ?? size,
                }),
                className,
            )}
            {...props}
        >
            {children}
        </ToggleGroupPrimitive.Item>
    );
}
