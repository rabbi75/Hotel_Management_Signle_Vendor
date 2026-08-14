import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';

export interface EmptyStateProps extends ComponentProps<'div'> {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
}

export function EmptyState({ className, icon: Icon, title, description, action, ...props }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-border p-10 text-center',
                className,
            )}
            {...props}
        >
            {Icon && (
                <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-muted">
                    <Icon className="size-6 text-muted-foreground" aria-hidden="true" />
                </div>
            )}
            <h3 className="text-sm font-semibold text-foreground">{title}</h3>
            {description && <p className="max-w-sm text-sm text-muted-foreground">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
