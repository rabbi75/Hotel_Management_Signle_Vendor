import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { ArrowDownRight, ArrowRight, ArrowUpRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Sparkline } from './sparkline';

export interface StatCardProps {
    label: string;
    value: ReactNode;
    /** Percentage change; the sign drives the direction. */
    delta?: number | null;
    deltaLabel?: string;
    /** Set when a fall is the good outcome, e.g. churn or latency. */
    invertDelta?: boolean;
    icon?: ReactNode;
    trend?: number[];
    trendColor?: string;
    footer?: ReactNode;
    loading?: boolean;
    className?: string;
}

export function StatCard({
    label,
    value,
    delta,
    deltaLabel,
    invertDelta = false,
    icon,
    trend,
    trendColor,
    footer,
    loading = false,
    className,
}: StatCardProps) {
    const direction = delta === null || delta === undefined || delta === 0 ? 'flat' : delta > 0 ? 'up' : 'down';
    const good = direction === 'flat' ? null : invertDelta ? direction === 'down' : direction === 'up';

    const DeltaIcon = direction === 'up' ? ArrowUpRight : direction === 'down' ? ArrowDownRight : ArrowRight;

    return (
        <Card className={cn('overflow-hidden', className)}>
            <CardContent className="space-y-3 p-5">
                <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-medium text-muted-foreground">{label}</p>
                    {icon && <span className="text-muted-foreground">{icon}</span>}
                </div>

                {loading ? (
                    <>
                        <Skeleton className="h-8 w-24" />
                        <Skeleton className="h-4 w-32" />
                    </>
                ) : (
                    <>
                        <div className="flex items-end justify-between gap-3">
                            <p className="text-2xl font-semibold tracking-tight tabular-nums">{value}</p>
                            {trend && trend.length > 1 && (
                                <Sparkline data={trend} color={trendColor ?? 'var(--chart-1)'} className="shrink-0" />
                            )}
                        </div>

                        {(delta !== null && delta !== undefined) || deltaLabel ? (
                            <p className="flex items-center gap-1.5 text-sm">
                                {delta !== null && delta !== undefined && (
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-0.5 font-medium tabular-nums',
                                            good === null && 'text-muted-foreground',
                                            good === true && 'text-success',
                                            good === false && 'text-destructive',
                                        )}
                                    >
                                        <DeltaIcon className="size-4" aria-hidden="true" />
                                        {Math.abs(delta).toFixed(1)}%
                                        <span className="sr-only">
                                            {direction === 'up' ? 'increase' : direction === 'down' ? 'decrease' : 'no change'}
                                        </span>
                                    </span>
                                )}
                                {deltaLabel && <span className="text-muted-foreground">{deltaLabel}</span>}
                            </p>
                        ) : null}

                        {footer && <div className="text-sm text-muted-foreground">{footer}</div>}
                    </>
                )}
            </CardContent>
        </Card>
    );
}
