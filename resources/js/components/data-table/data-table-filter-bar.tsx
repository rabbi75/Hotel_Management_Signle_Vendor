import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { TableFilter } from '@/types';
import { Check, ListFilter, Search, X } from 'lucide-react';
import { useId, useState } from 'react';
import type { DateRange } from 'react-day-picker';

export interface DataTableFilterBarProps {
    filters: TableFilter[];
    values: Record<string, unknown>;
    onChange: (key: string, value: unknown) => void;
    onClear: () => void;
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    activeCount: number;
    children?: React.ReactNode;
}

export function DataTableFilterBar({
    filters,
    values,
    onChange,
    onClear,
    search,
    onSearchChange,
    searchPlaceholder = 'Search…',
    activeCount,
    children,
}: DataTableFilterBarProps) {
    const searchId = useId();

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative min-w-0 flex-1 sm:max-w-xs">
                <Label htmlFor={searchId} className="sr-only">
                    Search this table
                </Label>
                <Search
                    className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id={searchId}
                    type="search"
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder={searchPlaceholder}
                    className="h-9 pl-8"
                />
            </div>

            {filters.map((filter) => (
                <FilterControl key={filter.key} filter={filter} value={values[filter.key]} onChange={onChange} />
            ))}

            {activeCount > 0 && (
                <Button variant="ghost" size="sm" onClick={onClear}>
                    <X className="size-4" aria-hidden="true" />
                    Reset
                    <Badge variant="secondary">{activeCount}</Badge>
                </Button>
            )}

            {children && <div className="ml-auto flex items-center gap-2">{children}</div>}
        </div>
    );
}

interface ControlProps {
    filter: TableFilter;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
}

function FilterControl({ filter, value, onChange }: ControlProps) {
    switch (filter.type) {
        case 'select':
            return filter.multiple ? (
                <MultiSelectFilter filter={filter} value={value} onChange={onChange} />
            ) : (
                <SingleSelectFilter filter={filter} value={value} onChange={onChange} />
            );
        case 'boolean':
            return <BooleanFilter filter={filter} value={value} onChange={onChange} />;
        case 'date_range':
            return <DateRangeFilter filter={filter} value={value} onChange={onChange} />;
        case 'text':
            return <TextFilter filter={filter} value={value} onChange={onChange} />;
    }
}

function SingleSelectFilter({ filter, value, onChange }: ControlProps) {
    const current = typeof value === 'string' || typeof value === 'number' ? String(value) : '';

    return (
        <Select value={current} onValueChange={(next) => onChange(filter.key, next === '__all__' ? null : next)}>
            <SelectTrigger className="h-9 w-auto min-w-36" aria-label={filter.label}>
                <SelectValue placeholder={filter.label} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="__all__">All {filter.label.toLowerCase()}</SelectItem>
                {filter.options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function MultiSelectFilter({ filter, value, onChange }: ControlProps) {
    const selected = Array.isArray(value) ? value.map(String) : [];

    function toggle(option: string): void {
        const next = selected.includes(option) ? selected.filter((entry) => entry !== option) : [...selected, option];

        onChange(filter.key, next);
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className="h-9 border-dashed">
                    <ListFilter className="size-4" aria-hidden="true" />
                    {filter.label}
                    {selected.length > 0 && (
                        <Badge variant="secondary" className="ml-1">
                            {selected.length}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-56 p-1">
                <ul role="listbox" aria-multiselectable="true" aria-label={filter.label} className="max-h-64 overflow-y-auto">
                    {filter.options.map((option) => {
                        const checked = selected.includes(option.value);

                        return (
                            <li key={option.value}>
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected={checked}
                                    onClick={() => toggle(option.value)}
                                    className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span
                                        className={cn(
                                            'flex size-4 shrink-0 items-center justify-center rounded-sm border border-border',
                                            checked && 'border-primary bg-primary text-primary-foreground',
                                        )}
                                        aria-hidden="true"
                                    >
                                        {checked && <Check className="size-3" />}
                                    </span>
                                    <span className="truncate">{option.label}</span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
                {selected.length > 0 && (
                    <Button variant="ghost" size="sm" className="mt-1 w-full" onClick={() => onChange(filter.key, [])}>
                        Clear
                    </Button>
                )}
            </PopoverContent>
        </Popover>
    );
}

function BooleanFilter({ filter, value, onChange }: ControlProps) {
    const id = useId();
    const checked = value === true || value === '1' || value === 1;

    return (
        <div className="flex h-9 items-center gap-2 rounded-md border border-dashed border-border px-3">
            <Checkbox id={id} checked={checked} onCheckedChange={(next) => onChange(filter.key, next === true ? true : null)} />
            <Label htmlFor={id} className="text-sm font-normal whitespace-nowrap">
                {filter.label}
            </Label>
        </div>
    );
}

function parseRange(value: unknown): DateRange | undefined {
    if (typeof value !== 'object' || value === null) {
        return undefined;
    }

    const record = value as Record<string, unknown>;
    const from = typeof record.from === 'string' ? new Date(record.from) : undefined;
    const to = typeof record.to === 'string' ? new Date(record.to) : undefined;

    return from ? { from, to } : undefined;
}

function toIsoDate(date: Date | undefined): string | undefined {
    return date ? date.toISOString().slice(0, 10) : undefined;
}

function DateRangeFilter({ filter, value, onChange }: ControlProps) {
    const [range, setRange] = useState<DateRange | undefined>(() => parseRange(value));

    return (
        <DatePicker
            mode="range"
            value={range}
            placeholder={filter.label}
            className="h-9 w-auto min-w-48"
            onChange={(next) => {
                setRange(next);
                onChange(filter.key, next?.from ? { from: toIsoDate(next.from), to: toIsoDate(next.to) } : null);
            }}
        />
    );
}

function TextFilter({ filter, value, onChange }: ControlProps) {
    const id = useId();
    const [local, setLocal] = useState(typeof value === 'string' ? value : '');

    return (
        <div>
            <Label htmlFor={id} className="sr-only">
                {filter.label}
            </Label>
            <Input
                id={id}
                value={local}
                placeholder={filter.label}
                className="h-9 w-40"
                onChange={(event) => setLocal(event.target.value)}
                onBlur={() => onChange(filter.key, local.trim() === '' ? null : local)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        onChange(filter.key, local.trim() === '' ? null : local);
                    }
                }}
            />
        </div>
    );
}
