import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

export interface PanelCardProps {
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
}

export function PanelCard({ title, description, action, children }: PanelCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <CardTitle className="text-base">{title}</CardTitle>
                    {description && <CardDescription>{description}</CardDescription>}
                </div>
                {action && <div className="flex shrink-0 flex-wrap items-center gap-2">{action}</div>}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export interface ErrorSummaryProps {
    errors: Record<string, string>;
    /** Field names already rendered inline; only what is left over is summarised. */
    handled?: string[];
    title?: string;
}

/**
 * Surfaces the errors no field owns — a transport failure, a policy rejection —
 * so a rejected submit is never silent.
 */
export function ErrorSummary({ errors, handled = [], title = 'That change could not be saved' }: ErrorSummaryProps) {
    const orphans = Object.entries(errors).filter(([field]) => !handled.includes(field) && !handled.includes(field.split('.')[0] ?? field));

    if (orphans.length === 0) {
        return null;
    }

    return (
        <Alert variant="destructive" role="alert" className="mb-6">
            <CircleAlert aria-hidden="true" />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription>
                <ul className="list-inside list-disc break-words">
                    {orphans.map(([field, message]) => (
                        <li key={field}>{message}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}

/** Placeholder shown while a panel's props are still in flight on a partial reload. */
export function PanelSkeleton({ rows = 4 }: { rows?: number }) {
    return (
        <div className="space-y-4" aria-hidden="true">
            {Array.from({ length: rows }, (_, index) => (
                <div key={index} className="grid gap-2">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-9 w-full" />
                </div>
            ))}
        </div>
    );
}
