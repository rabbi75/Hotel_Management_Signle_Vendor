import { Bar, CartesianGrid, LabelList, Legend, BarChart as RechartsBarChart, Tooltip, XAxis, YAxis } from 'recharts';
import {
    AXIS_PROPS,
    chartColor,
    ChartContainer,
    ChartTooltip,
    GRID_PROPS,
    toTooltipProps,
    type ChartContainerProps,
    type ChartSeries,
} from './chart-container';

export interface BarChartProps<TDatum extends Record<string, unknown>> extends Omit<ChartContainerProps, 'children' | 'isEmpty'> {
    data: TDatum[];
    xKey: string;
    series: ChartSeries[];
    stacked?: boolean;
    horizontal?: boolean;
    /** Prints the value above each bar — the most reliable way to read one. */
    showValues?: boolean;
    valueFormatter?: (value: number | string, name: string) => string;
    xTickFormatter?: (value: string) => string;
}

export function BarChart<TDatum extends Record<string, unknown>>({
    data,
    xKey,
    series,
    stacked = false,
    horizontal = false,
    showValues = false,
    valueFormatter,
    xTickFormatter,
    ...container
}: BarChartProps<TDatum>) {
    return (
        <ChartContainer {...container} isEmpty={data.length === 0}>
            <RechartsBarChart
                data={data}
                layout={horizontal ? 'vertical' : 'horizontal'}
                margin={{ top: showValues ? 20 : 8, right: 8, bottom: 0, left: horizontal ? 8 : -12 }}
                barCategoryGap={horizontal ? '20%' : '25%'}
            >
                <CartesianGrid {...GRID_PROPS} vertical={horizontal} horizontal={!horizontal} />

                {horizontal ? (
                    <>
                        <XAxis type="number" {...AXIS_PROPS} />
                        <YAxis type="category" dataKey={xKey} {...AXIS_PROPS} width={96} tickFormatter={xTickFormatter} />
                    </>
                ) : (
                    <>
                        <XAxis dataKey={xKey} {...AXIS_PROPS} tickFormatter={xTickFormatter} minTickGap={8} />
                        <YAxis {...AXIS_PROPS} width={48} />
                    </>
                )}

                <Tooltip
                    cursor={{ fill: 'var(--muted)', opacity: 0.4 }}
                    content={(props) => <ChartTooltip {...toTooltipProps(props)} valueFormatter={valueFormatter} />}
                />
                {series.length > 1 && <Legend iconType="square" wrapperStyle={{ fontSize: 12, paddingTop: 8 }} />}

                {series.map((entry, index) => (
                    <Bar
                        key={entry.key}
                        dataKey={entry.key}
                        name={entry.label}
                        stackId={stacked ? 'stack' : undefined}
                        fill={entry.color ?? chartColor(index)}
                        radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
                        maxBarSize={48}
                    >
                        {showValues && (
                            <LabelList
                                dataKey={entry.key}
                                position={horizontal ? 'right' : 'top'}
                                className="fill-muted-foreground"
                                fontSize={11}
                            />
                        )}
                    </Bar>
                ))}
            </RechartsBarChart>
        </ChartContainer>
    );
}
