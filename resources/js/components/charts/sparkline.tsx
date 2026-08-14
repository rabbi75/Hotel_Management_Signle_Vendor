import { cn } from '@/lib/utils';
import { useId } from 'react';

export interface SparklineProps {
    data: number[];
    width?: number;
    height?: number;
    /** Any CSS colour; defaults to the first chart token. */
    color?: string;
    /** Fills the area under the line with a fading gradient. */
    filled?: boolean;
    /** Marks the final point, which is the one people actually look for. */
    showLastPoint?: boolean;
    label?: string;
    className?: string;
}

/**
 * A hand-rolled SVG sparkline.
 *
 * Recharts carries a per-instance cost that is not worth paying for a 60×20
 * decoration repeated across a table or a row of stat cards.
 */
export function Sparkline({
    data,
    width = 96,
    height = 28,
    color = 'var(--chart-1)',
    filled = true,
    showLastPoint = true,
    label,
    className,
}: SparklineProps) {
    const gradientId = useId();

    if (data.length < 2) {
        return <div className={cn('h-7', className)} style={{ width }} aria-hidden="true" />;
    }

    const min = Math.min(...data);
    const max = Math.max(...data);
    const span = max - min || 1;
    const padding = 2;
    const usableHeight = height - padding * 2;

    const points = data.map((value, index) => {
        const x = (index / (data.length - 1)) * width;
        const y = padding + usableHeight - ((value - min) / span) * usableHeight;

        return { x, y };
    });

    const line = points.map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x.toFixed(2)},${point.y.toFixed(2)}`).join(' ');
    const area = `${line} L${width},${height} L0,${height} Z`;
    const last = points[points.length - 1];

    return (
        <svg
            width={width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
            className={cn('overflow-visible', className)}
            role={label ? 'img' : 'presentation'}
            aria-label={label}
            aria-hidden={label ? undefined : true}
            preserveAspectRatio="none"
        >
            {filled && (
                <>
                    <defs>
                        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={color} stopOpacity={0.28} />
                            <stop offset="100%" stopColor={color} stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <path d={area} fill={`url(#${gradientId})`} />
                </>
            )}

            <path
                d={line}
                fill="none"
                stroke={color}
                strokeWidth={1.5}
                strokeLinecap="round"
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
            />

            {showLastPoint && last && <circle cx={last.x} cy={last.y} r={2} fill={color} />}
        </svg>
    );
}
