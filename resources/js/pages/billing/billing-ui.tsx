import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import type { UsageMeter } from '@/types/billing';

/**
 * The server sends a semantic colour token per enum case; this is the one place
 * that maps it onto a badge variant, so a new status never has to be styled in
 * three screens at once.
 */
const VARIANTS: Record<string, NonNullable<BadgeProps['variant']>> = {
    primary: 'default',
    success: 'success',
    warning: 'warning',
    info: 'info',
    destructive: 'destructive',
    neutral: 'secondary',
};

export function StatusBadge({ label, color }: { label: string; color: string }) {
    return <Badge variant={VARIANTS[color] ?? 'secondary'}>{label}</Badge>;
}

/** Money already formatted server-side; rendered with tabular figures so columns line up. */
export function Amount({ value, className }: { value: string; className?: string }) {
    return <span className={cn('tabular-nums', className)}>{value}</span>;
}

export function UsageMeterBar({ meter }: { meter: UsageMeter }) {
    const unlimited = meter.limit < 0;
    const percentage = meter.percentage ?? 0;
    const nearLimit = !unlimited && percentage >= 80;
    const atLimit = !unlimited && percentage >= 100;

    return (
        <div className="space-y-1.5">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="text-sm font-medium">{meter.label}</span>
                <span className={cn('text-xs tabular-nums', atLimit ? 'text-destructive' : 'text-muted-foreground')}>
                    {unlimited ? (
                        <>{meter.used.toLocaleString()} used — unlimited</>
                    ) : (
                        <>
                            {meter.used.toLocaleString()} / {meter.limit.toLocaleString()}
                        </>
                    )}
                </span>
            </div>

            {!unlimited && (
                <Progress
                    value={Math.min(100, percentage)}
                    aria-label={`${meter.label} usage`}
                    // Colour alone never carries the meaning: the numeric label
                    // above says the same thing.
                    className={cn(atLimit && '[&>div]:bg-destructive', !atLimit && nearLimit && '[&>div]:bg-warning')}
                />
            )}

            {atLimit && <p className="text-xs text-destructive">You have reached this allowance. Upgrade to continue.</p>}
        </div>
    );
}
