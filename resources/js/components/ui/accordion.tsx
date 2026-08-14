import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-react';
import { Accordion as AccordionPrimitive } from 'radix-ui';
import type { ComponentProps } from 'react';

export const Accordion = AccordionPrimitive.Root;

export function AccordionItem({ className, ...props }: ComponentProps<typeof AccordionPrimitive.Item>) {
    return <AccordionPrimitive.Item className={cn('border-b border-border last:border-b-0', className)} {...props} />;
}

export function AccordionTrigger({ className, children, ...props }: ComponentProps<typeof AccordionPrimitive.Trigger>) {
    return (
        <AccordionPrimitive.Header className="flex">
            <AccordionPrimitive.Trigger
                className={cn(
                    'flex flex-1 items-center justify-between gap-4 rounded-md py-4 text-left text-sm font-medium transition-all outline-none',
                    'hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                    'disabled:pointer-events-none disabled:opacity-50',
                    '[&[data-state=open]>svg]:rotate-180',
                    className,
                )}
                {...props}
            >
                {children}
                <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform duration-200" />
            </AccordionPrimitive.Trigger>
        </AccordionPrimitive.Header>
    );
}

export function AccordionContent({ className, children, ...props }: ComponentProps<typeof AccordionPrimitive.Content>) {
    return (
        <AccordionPrimitive.Content
            className="overflow-hidden text-sm data-[state=closed]:animate-accordion-up data-[state=open]:animate-accordion-down"
            {...props}
        >
            <div className={cn('pt-0 pb-4', className)}>{children}</div>
        </AccordionPrimitive.Content>
    );
}
