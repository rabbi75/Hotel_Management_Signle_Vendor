import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import type { DateRange } from 'react-day-picker';

interface DatePickerSharedProps {
    className?: string;
    placeholder?: string;
    disabled?: boolean;
}

export interface DatePickerProps extends DatePickerSharedProps {
    mode?: 'single';
    value?: Date;
    onChange?: (date: Date | undefined) => void;
}

export interface DateRangePickerProps extends DatePickerSharedProps {
    mode: 'range';
    value?: DateRange;
    onChange?: (range: DateRange | undefined) => void;
}

function formatLabel(props: DatePickerProps | DateRangePickerProps): string {
    if (props.mode === 'range') {
        const { value } = props;
        if (!value?.from) {
            return props.placeholder ?? 'Pick a date range';
        }
        if (!value.to) {
            return format(value.from, 'PP');
        }
        return `${format(value.from, 'PP')} – ${format(value.to, 'PP')}`;
    }

    return props.value ? format(props.value, 'PP') : (props.placeholder ?? 'Pick a date');
}

/** Popover-triggered calendar. Pass `mode="range"` to select a start/end pair instead of a single date. */
export function DatePicker(props: DatePickerProps | DateRangePickerProps) {
    const { className, disabled } = props;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    disabled={disabled}
                    className={cn(
                        'w-64 justify-start text-left font-normal',
                        props.mode === 'range' ? !props.value?.from && 'text-muted-foreground' : !props.value && 'text-muted-foreground',
                        className,
                    )}
                >
                    <CalendarIcon className="opacity-60" />
                    {formatLabel(props)}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                {props.mode === 'range' ? (
                    <Calendar mode="range" selected={props.value} onSelect={props.onChange} numberOfMonths={2} autoFocus />
                ) : (
                    <Calendar mode="single" selected={props.value} onSelect={props.onChange} autoFocus />
                )}
            </PopoverContent>
        </Popover>
    );
}
