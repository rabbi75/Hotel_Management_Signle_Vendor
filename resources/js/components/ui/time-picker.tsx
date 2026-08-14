import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

export interface TimePickerProps {
    value?: Date;
    onChange?: (date: Date) => void;
    hourCycle?: 12 | 24;
    showSeconds?: boolean;
    disabled?: boolean;
    className?: string;
}

function pad(n: number): string {
    return n.toString().padStart(2, '0');
}

function withField(base: Date, field: 'hours' | 'minutes' | 'seconds' | 'meridiem', numericValue: number): Date {
    const next = new Date(base);

    if (field === 'hours') {
        const isPm = next.getHours() >= 12;
        next.setHours(isPm ? ((numericValue % 12) + 12) % 24 : numericValue % 12);
    } else if (field === 'minutes') {
        next.setMinutes(numericValue);
    } else if (field === 'seconds') {
        next.setSeconds(numericValue);
    } else {
        const hours12 = next.getHours() % 12;
        next.setHours(numericValue === 1 ? hours12 + 12 : hours12);
    }

    return next;
}

/** Hour / minute / (optional) second selects backed by a single `Date`. Supports 12h (with AM/PM) and 24h cycles. */
export function TimePicker({ value, onChange, hourCycle = 24, showSeconds = false, disabled, className }: TimePickerProps) {
    const current = value ?? new Date(0, 0, 0, 0, 0, 0);
    const hours24 = current.getHours();
    const isPm = hours24 >= 12;
    const displayHours = hourCycle === 12 ? (hours24 % 12 === 0 ? 12 : hours24 % 12) : hours24;
    const hourOptions = hourCycle === 12 ? Array.from({ length: 12 }, (_, i) => i + 1) : Array.from({ length: 24 }, (_, i) => i);
    const minuteSecondOptions = Array.from({ length: 60 }, (_, i) => i);

    function emit(next: Date): void {
        onChange?.(next);
    }

    return (
        <div className={cn('flex items-center gap-1.5', className)}>
            <Select disabled={disabled} value={String(displayHours)} onValueChange={(v) => emit(withField(current, 'hours', Number(v)))}>
                <SelectTrigger className="w-[4.5rem]" aria-label="Hour">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {hourOptions.map((hour) => (
                        <SelectItem key={hour} value={String(hour)}>
                            {pad(hour)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <span className="text-muted-foreground">:</span>
            <Select
                disabled={disabled}
                value={String(current.getMinutes())}
                onValueChange={(v) => emit(withField(current, 'minutes', Number(v)))}
            >
                <SelectTrigger className="w-[4.5rem]" aria-label="Minute">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {minuteSecondOptions.map((minute) => (
                        <SelectItem key={minute} value={String(minute)}>
                            {pad(minute)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {showSeconds && (
                <>
                    <span className="text-muted-foreground">:</span>
                    <Select
                        disabled={disabled}
                        value={String(current.getSeconds())}
                        onValueChange={(v) => emit(withField(current, 'seconds', Number(v)))}
                    >
                        <SelectTrigger className="w-[4.5rem]" aria-label="Second">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {minuteSecondOptions.map((second) => (
                                <SelectItem key={second} value={String(second)}>
                                    {pad(second)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </>
            )}
            {hourCycle === 12 && (
                <Select disabled={disabled} value={isPm ? '1' : '0'} onValueChange={(v) => emit(withField(current, 'meridiem', Number(v)))}>
                    <SelectTrigger className="w-[4.5rem]" aria-label="AM or PM">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="0">AM</SelectItem>
                        <SelectItem value="1">PM</SelectItem>
                    </SelectContent>
                </Select>
            )}
        </div>
    );
}
