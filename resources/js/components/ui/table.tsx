import { cn } from '@/lib/utils';
import type { ComponentProps } from 'react';

export function Table({ className, ...props }: ComponentProps<'table'>) {
    return (
        <div className="relative w-full overflow-x-auto">
            <table className={cn('w-full caption-bottom text-sm', className)} {...props} />
        </div>
    );
}

export interface TableHeaderProps extends ComponentProps<'thead'> {
    sticky?: boolean;
}

export function TableHeader({ className, sticky = false, ...props }: TableHeaderProps) {
    return (
        <thead className={cn('[&_tr]:border-b [&_tr]:border-border', sticky && 'sticky top-0 z-10 bg-background', className)} {...props} />
    );
}

export function TableBody({ className, ...props }: ComponentProps<'tbody'>) {
    return <tbody className={cn('[&_tr:last-child]:border-0', className)} {...props} />;
}

export function TableFooter({ className, ...props }: ComponentProps<'tfoot'>) {
    return <tfoot className={cn('border-t border-border bg-muted/50 font-medium [&>tr]:last:border-b-0', className)} {...props} />;
}

export function TableRow({ className, ...props }: ComponentProps<'tr'>) {
    return (
        <tr
            className={cn('border-b border-border transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted', className)}
            {...props}
        />
    );
}

export function TableHead({ className, ...props }: ComponentProps<'th'>) {
    return (
        <th
            className={cn(
                'h-10 px-3 text-left align-middle font-medium whitespace-nowrap text-muted-foreground [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]',
                className,
            )}
            {...props}
        />
    );
}

export function TableCell({ className, ...props }: ComponentProps<'td'>) {
    return (
        <td
            className={cn(
                'p-3 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]',
                className,
            )}
            {...props}
        />
    );
}

export function TableCaption({ className, ...props }: ComponentProps<'caption'>) {
    return <caption className={cn('mt-4 text-sm text-muted-foreground', className)} {...props} />;
}
