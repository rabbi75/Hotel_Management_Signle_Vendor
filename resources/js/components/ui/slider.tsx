import { cn } from '@/lib/utils';
import { Slider as SliderPrimitive } from 'radix-ui';
import { type ComponentProps, useMemo } from 'react';

export function Slider({ className, defaultValue, value, min = 0, max = 100, ...props }: ComponentProps<typeof SliderPrimitive.Root>) {
    const values = useMemo(
        () => (Array.isArray(value) ? value : Array.isArray(defaultValue) ? defaultValue : [min, max]),
        [value, defaultValue, min, max],
    );

    return (
        <SliderPrimitive.Root
            defaultValue={defaultValue}
            value={value}
            min={min}
            max={max}
            className={cn(
                'relative flex w-full touch-none items-center select-none data-[disabled]:opacity-50',
                'data-[orientation=vertical]:h-full data-[orientation=vertical]:min-h-44 data-[orientation=vertical]:w-auto data-[orientation=vertical]:flex-col',
                className,
            )}
            {...props}
        >
            <SliderPrimitive.Track className="relative grow overflow-hidden rounded-full bg-secondary data-[orientation=horizontal]:h-1.5 data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-1.5">
                <SliderPrimitive.Range className="absolute rounded-full bg-primary data-[orientation=horizontal]:h-full data-[orientation=vertical]:w-full" />
            </SliderPrimitive.Track>
            {values.map((_, index) => (
                <SliderPrimitive.Thumb
                    key={index}
                    className="block size-4 shrink-0 rounded-full border border-primary bg-background shadow-sm transition-[color,box-shadow] outline-none hover:ring-4 hover:ring-ring/20 focus-visible:ring-4 focus-visible:ring-ring/40 disabled:pointer-events-none disabled:opacity-50"
                />
            ))}
        </SliderPrimitive.Root>
    );
}
