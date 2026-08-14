import { Cell, Pie, PieChart, Tooltip } from 'recharts';
import { chartColor, ChartContainer, ChartTooltip, toTooltipProps, type ChartContainerProps } from './chart-container';

export interface DonutSlice {
    label: string;
    value: number;
    color?: string;
}

export interface DonutChartProps extends Omit<ChartContainerProps, 'children' | 'isEmpty'> {
    data: DonutSlice[];
    /** Big number rendered in the hole. Defaults to the total. */
    centerLabel?: string;
    centerCaption?: string;
    valueFormatter?: (value: number) => string;
}

export function DonutChart({ data, centerLabel, centerCaption, valueFormatter, height = 260, ...container }: DonutChartProps) {
    const total = data.reduce((sum, slice) => sum + slice.value, 0);
    const format = valueFormatter ?? ((value: number) => new Intl.NumberFormat().format(value));

    return (
        <div className="relative">
            <ChartContainer {...container} height={height} isEmpty={data.length === 0 || total === 0}>
                <PieChart>
                    <Tooltip
                        content={(props) => (
                            <ChartTooltip
                                {...toTooltipProps(props)}
                                valueFormatter={(value) => {
                                    const numeric = typeof value === 'number' ? value : Number(value);

                                    return `${format(numeric)} · ${total > 0 ? Math.round((numeric / total) * 100) : 0}%`;
                                }}
                            />
                        )}
                    />
                    <Pie
                        data={data}
                        dataKey="value"
                        nameKey="label"
                        innerRadius="58%"
                        outerRadius="82%"
                        paddingAngle={2}
                        strokeWidth={2}
                        stroke="var(--background)"
                    >
                        {data.map((slice, index) => (
                            <Cell key={slice.label} fill={slice.color ?? chartColor(index)} />
                        ))}
                    </Pie>
                </PieChart>
            </ChartContainer>

            {total > 0 && (
                <div className="pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 text-center" aria-hidden="true">
                    <p className="text-2xl font-semibold tracking-tight tabular-nums">{centerLabel ?? format(total)}</p>
                    {centerCaption && <p className="text-xs text-muted-foreground">{centerCaption}</p>}
                </div>
            )}

            {/* A direct legend with the share printed out — reading a donut by
                colour alone is unreliable, and this is also the text alternative. */}
            <ul className="mt-3 grid gap-1.5 sm:grid-cols-2">
                {data.map((slice, index) => (
                    <li key={slice.label} className="flex items-center gap-2 text-sm">
                        <span
                            className="size-2.5 shrink-0 rounded-[3px]"
                            style={{ backgroundColor: slice.color ?? chartColor(index) }}
                            aria-hidden="true"
                        />
                        <span className="min-w-0 flex-1 truncate text-muted-foreground">{slice.label}</span>
                        <span className="font-medium tabular-nums">{format(slice.value)}</span>
                        <span className="w-10 text-right text-xs text-muted-foreground tabular-nums">
                            {total > 0 ? Math.round((slice.value / total) * 100) : 0}%
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
