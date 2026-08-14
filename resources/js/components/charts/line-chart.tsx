import { CartesianGrid, Legend, Line, LineChart as RechartsLineChart, Tooltip, XAxis, YAxis } from 'recharts';
import {
    AXIS_PROPS,
    chartColor,
    ChartContainer,
    chartDash,
    chartShape,
    ChartTooltip,
    GRID_PROPS,
    toTooltipProps,
    type ChartContainerProps,
    type ChartSeries,
} from './chart-container';

export interface LineChartProps<TDatum extends Record<string, unknown>> extends Omit<ChartContainerProps, 'children' | 'isEmpty'> {
    data: TDatum[];
    xKey: string;
    series: ChartSeries[];
    /** Draws a marker at every point. Off for dense series, where it becomes noise. */
    showDots?: boolean;
    valueFormatter?: (value: number | string, name: string) => string;
    xTickFormatter?: (value: string) => string;
}

export function LineChart<TDatum extends Record<string, unknown>>({
    data,
    xKey,
    series,
    showDots = true,
    valueFormatter,
    xTickFormatter,
    ...container
}: LineChartProps<TDatum>) {
    return (
        <ChartContainer {...container} isEmpty={data.length === 0}>
            <RechartsLineChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: -12 }}>
                <CartesianGrid {...GRID_PROPS} />
                <XAxis dataKey={xKey} {...AXIS_PROPS} tickFormatter={xTickFormatter} minTickGap={16} />
                <YAxis {...AXIS_PROPS} width={48} />
                <Tooltip
                    cursor={{ stroke: 'var(--border)' }}
                    content={(props) => <ChartTooltip {...toTooltipProps(props)} valueFormatter={valueFormatter} />}
                />
                {series.length > 1 && <Legend iconType="plainline" wrapperStyle={{ fontSize: 12, paddingTop: 8 }} />}

                {series.map((entry, index) => {
                    const color = entry.color ?? chartColor(index);

                    return (
                        <Line
                            key={entry.key}
                            type="monotone"
                            dataKey={entry.key}
                            name={entry.label}
                            stroke={color}
                            strokeWidth={2}
                            strokeDasharray={chartDash(index)}
                            // A distinct marker per series keeps them apart without colour.
                            dot={showDots ? { r: 3, fill: color, strokeWidth: 0 } : false}
                            activeDot={{ r: 5, strokeWidth: 2, stroke: 'var(--background)' }}
                            legendType={chartShape(index)}
                        />
                    );
                })}
            </RechartsLineChart>
        </ChartContainer>
    );
}
