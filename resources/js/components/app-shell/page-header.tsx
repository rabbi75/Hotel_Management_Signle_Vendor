import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

export interface PageHeaderProps {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
    className?: string;
    /** Renders the title as an `<h2>` when the page already owns the `<h1>`. */
    level?: 1 | 2;
}

export function PageHeader({ title, description, actions, className, level = 1 }: PageHeaderProps) {
    const Heading = level === 1 ? 'h1' : 'h2';

    return (
        <div className={cn('flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between', className)}>
            <div className="min-w-0 space-y-1">
                <Heading className="truncate text-xl font-semibold tracking-tight text-foreground">{title}</Heading>
                {description && <p className="text-sm text-balance text-muted-foreground">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
