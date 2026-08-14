import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, MoreHorizontal } from 'lucide-react';
import type { ComponentProps } from 'react';

export function Pagination({ className, ...props }: ComponentProps<'nav'>) {
    return <nav role="navigation" aria-label="pagination" className={cn('mx-auto flex w-full justify-center', className)} {...props} />;
}

export function PaginationContent({ className, ...props }: ComponentProps<'ul'>) {
    return <ul className={cn('flex flex-row items-center gap-1', className)} {...props} />;
}

export function PaginationItem({ ...props }: ComponentProps<'li'>) {
    return <li {...props} />;
}

export interface PaginationLinkProps extends ComponentProps<'a'> {
    isActive?: boolean;
    size?: 'default' | 'icon';
}

export function PaginationLink({ className, isActive, size = 'icon', ...props }: PaginationLinkProps) {
    return (
        <a
            aria-current={isActive ? 'page' : undefined}
            className={cn(buttonVariants({ variant: isActive ? 'outline' : 'ghost', size }), className)}
            {...props}
        />
    );
}

export function PaginationPrevious({ className, ...props }: ComponentProps<typeof PaginationLink>) {
    return (
        <PaginationLink aria-label="Go to previous page" size="default" className={cn('gap-1 px-2.5', className)} {...props}>
            <ChevronLeft />
            <span>Previous</span>
        </PaginationLink>
    );
}

export function PaginationNext({ className, ...props }: ComponentProps<typeof PaginationLink>) {
    return (
        <PaginationLink aria-label="Go to next page" size="default" className={cn('gap-1 px-2.5', className)} {...props}>
            <span>Next</span>
            <ChevronRight />
        </PaginationLink>
    );
}

export function PaginationEllipsis({ className, ...props }: ComponentProps<'span'>) {
    return (
        <span aria-hidden="true" className={cn('flex size-9 items-center justify-center', className)} {...props}>
            <MoreHorizontal className="size-4" />
            <span className="sr-only">More pages</span>
        </span>
    );
}
