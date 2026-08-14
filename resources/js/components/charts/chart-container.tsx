import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';
import { ResponsiveContainer } from 'recharts';

/**
 * The six data-visualisation tokens, hue-spaced at equal chroma so no series
 * dominates and the set stays legible in both themes.
 */
export const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--chart-6)',
] as const;

/**
 * Dash patterns and marker shapes paired with each colour.
 *
 * Colour alone is not a reliable channel — for colour-blind readers, in print,
 * or on a projector — so every series also differs in stroke pattern and, where
 * the chart type allows, in marker shape.
 */
export const CHART_DASHES = ['0', '6 3', '2 3', '10 4 2 4', '4 2', '1 4'] as const;
export const CHART_SHAPES = ['circle', 'square', 'triangle', 'diamond', 'star', 'cross'] as const;

export type ChartShape = (typeof CHART_SHAPES)[number];

export function chartColor(index: number): string {
    return CHART_COLORS[index % CHART_COLORS.length] ?? CHART_COLORS[0];
}

export function chartDash(index: number): string {
    return CHART_DASHES[index % CHART_DASHES.length] ?? '0';
}

export function chartShape(index: number): ChartShape {
    return CHART_SHAPES[index % CHART_SHAPES.length] ?? 'circle';
}

export interface ChartSeries {
    /** Key in each datum holding this series' value. */
    key: string;
    label: string;
    /** Overrides the token picked from the palette by position. */
    color?: string;
}

/** Shared axis and grid styling, so every chart in the kit reads as one system. */
export const AXIS_PROPS = {
    stroke: 'var(--muted-foreground)',
    fontSize: 12,
    tickLine: false,
    axisLine: false,
} as const;

export const GRID_PROPS = {
    stroke: 'var(--border)',
    strokeDasharray: '3 3',
    vertical: false,
} as const;

export interface ChartContainerProps {
    title?: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    /** Fixed pixel height of the plot area. */
    height?: number;
    loading?: boolean;
    /** Rendered instead of the chart when there is no data. */
    empty?: ReactNode;
    isEmpty?: boolean;
    /** Text alternative for screen readers; charts are otherwise opaque. */
    summary?: string;
    children: ReactNode;
    className?: string;
}

export function ChartContainer({
    title,
    description,
    action,
    height = 260,
    loading = false,
    empty,
    isEmpty = false,
    summary,
    children,
    className,
}: ChartContainerProps) {
    return (
        <figure className={cn('flex flex-col gap-3', className)}>
            {(title || description || action) && (
                <figcaption className="flex items-start justify-between gap-3">
                    <div className="min-w-0 space-y-0.5">
                        {title && <div className="text-sm font-medium text-foreground">{title}</div>}
                        {description && <div className="text-sm text-muted-foreground">{description}</div>}
                    </div>
                    {action && <div className="shrink-0">{action}</div>}
                </figcaption>
            )}

            {loading ? (
                <div className="space-y-2" style={{ height }} aria-busy="true">
                    <Skeleton className="h-full w-full" />
                </div>
            ) : isEmpty ? (
                <div className="flex items-center justify-center rounded-lg border border-dashed border-border" style={{ height }}>
                    {empty ?? <p className="text-sm text-muted-foreground">No data for this period.</p>}
                </div>
            ) : (
                <div style={{ height }} role="img" aria-label={summary}>
                    <ResponsiveContainer width="100%" height="100%">
                        {children as React.ReactElement}
                    </ResponsiveContainer>
                </div>
            )}
        </figure>
    );
}

export interface ChartTooltipEntry {
    name?: unknown;
    dataKey?: unknown;
    value?: unknown;
    color?: string | undefined;
}

/**
 * Structurally compatible with Recharts' tooltip content props without
 * extending them: the library's `labelFormatter` signature collides with the
 * narrower one this component wants to expose.
 */
export interface ChartTooltipProps {
    active?: boolean | undefined;
    payload?: readonly ChartTooltipEntry[] | undefined;
    label?: unknown;
    /** Formats each value; defaults to a localised number. */
    valueFormatter?: ((value: number | string, name: string) => string) | undefined;
    labelFormatter?: ((label: unknown) => string) | undefined;
}

/** Adapts Recharts' `content` callback props onto {@link ChartTooltipProps}. */
export function toTooltipProps(props: {
    active?: boolean | undefined;
    payload?: readonly ChartTooltipEntry[] | undefined;
    label?: unknown;
}): ChartTooltipProps {
    return { active: props.active, payload: props.payload, label: props.label };
}

function defaultValue(value: number | string): string {
    return typeof value === 'number' ? new Intl.NumberFormat().format(value) : String(value);
}

/** The one tooltip every chart uses, styled from the popover tokens. */
export function ChartTooltip({ active, payload, label, valueFormatter, labelFormatter }: ChartTooltipProps) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    return (
        <div className="min-w-36 rounded-lg border border-border bg-popover px-3 py-2 text-popover-foreground shadow-md">
            <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                {labelFormatter ? labelFormatter(label) : String(label ?? '')}
            </p>
            <ul className="space-y-1">
                {payload.map((entry, index) => {
                    const name = String(entry.name ?? entry.dataKey ?? '');
                    const raw = entry.value;
                    const value: number | string = typeof raw === 'number' || typeof raw === 'string' ? raw : 0;

                    return (
                        <li key={`${name}-${index}`} className="flex items-center gap-2 text-xs">
                            <span
                                className="size-2 shrink-0 rounded-[2px]"
                                style={{ backgroundColor: entry.color ?? chartColor(index) }}
                                aria-hidden="true"
                            />
                            <span className="flex-1 truncate text-muted-foreground">{name}</span>
                            <span className="font-medium tabular-nums">
                                {valueFormatter ? valueFormatter(value, name) : defaultValue(value)}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
