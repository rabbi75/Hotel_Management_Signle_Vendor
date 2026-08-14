import { Area, CartesianGrid, Legend, AreaChart as RechartsAreaChart, Tooltip, XAxis, YAxis } from 'recharts';
import {
    AXIS_PROPS,
    chartColor,
    ChartContainer,
    chartDash,
    ChartTooltip,
    GRID_PROPS,
    toTooltipProps,
    type ChartContainerProps,
    type ChartSeries,
} from './chart-container';

export interface AreaChartProps<TDatum extends Record<string, unknown>> extends Omit<ChartContainerProps, 'children' | 'isEmpty'> {
    data: TDatum[];
    /** Datum key used for the X axis. */
    xKey: string;
    series: ChartSeries[];
    stacked?: boolean;
    valueFormatter?: (value: number | string, name: string) => string;
    xTickFormatter?: (value: string) => string;
}

export function AreaChart<TDatum extends Record<string, unknown>>({
    data,
    xKey,
    series,
    stacked = false,
    valueFormatter,
    xTickFormatter,
    ...container
}: AreaChartProps<TDatum>) {
    return (
        <ChartContainer {...container} isEmpty={data.length === 0}>
            <RechartsAreaChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: -12 }}>
                <defs>
                    {series.map((entry, index) => (
                        <linearGradient key={entry.key} id={`area-fill-${entry.key}`} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={entry.color ?? chartColor(index)} stopOpacity={0.35} />
                            <stop offset="100%" stopColor={entry.color ?? chartColor(index)} stopOpacity={0.02} />
                        </linearGradient>
                    ))}
                </defs>

                <CartesianGrid {...GRID_PROPS} />
                <XAxis dataKey={xKey} {...AXIS_PROPS} tickFormatter={xTickFormatter} minTickGap={16} />
                <YAxis {...AXIS_PROPS} width={48} />
                <Tooltip
                    cursor={{ stroke: 'var(--border)' }}
                    content={(props) => <ChartTooltip {...toTooltipProps(props)} valueFormatter={valueFormatter} />}
                />
                {series.length > 1 && <Legend iconType="plainline" wrapperStyle={{ fontSize: 12, paddingTop: 8 }} />}

                {series.map((entry, index) => (
                    <Area
                        key={entry.key}
                        type="monotone"
                        dataKey={entry.key}
                        name={entry.label}
                        stackId={stacked ? 'stack' : undefined}
                        stroke={entry.color ?? chartColor(index)}
                        strokeWidth={2}
                        strokeDasharray={chartDash(index)}
                        fill={`url(#area-fill-${entry.key})`}
                        activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--background)' }}
                    />
                ))}
            </RechartsAreaChart>
        </ChartContainer>
    );
}
