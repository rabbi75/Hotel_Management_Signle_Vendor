import { cn } from '@/lib/utils';
import { ChevronRight, MoreHorizontal } from 'lucide-react';
import { Slot } from 'radix-ui';
import type { ComponentProps } from 'react';

export function Breadcrumb({ ...props }: ComponentProps<'nav'>) {
    return <nav aria-label="breadcrumb" {...props} />;
}

export function BreadcrumbList({ className, ...props }: ComponentProps<'ol'>) {
    return (
        <ol
            className={cn('flex flex-wrap items-center gap-1.5 text-sm break-words text-muted-foreground sm:gap-2.5', className)}
            {...props}
        />
    );
}

export function BreadcrumbItem({ className, ...props }: ComponentProps<'li'>) {
    return <li className={cn('inline-flex items-center gap-1.5', className)} {...props} />;
}

export function BreadcrumbLink({ className, asChild, ...props }: ComponentProps<'a'> & { asChild?: boolean }) {
    const Comp = asChild ? Slot.Root : 'a';

    return <Comp className={cn('transition-colors hover:text-foreground', className)} {...props} />;
}

export function BreadcrumbPage({ className, ...props }: ComponentProps<'span'>) {
    return (
        <span role="link" aria-disabled="true" aria-current="page" className={cn('font-normal text-foreground', className)} {...props} />
    );
}

export function BreadcrumbSeparator({ className, children, ...props }: ComponentProps<'li'>) {
    return (
        <li role="presentation" aria-hidden="true" className={cn('[&>svg]:size-3.5', className)} {...props}>
            {children ?? <ChevronRight />}
        </li>
    );
}

export function BreadcrumbEllipsis({ className, ...props }: ComponentProps<'span'>) {
    return (
        <span role="presentation" aria-hidden="true" className={cn('flex size-9 items-center justify-center', className)} {...props}>
            <MoreHorizontal className="size-4" />
            <span className="sr-only">More</span>
        </span>
    );
}
