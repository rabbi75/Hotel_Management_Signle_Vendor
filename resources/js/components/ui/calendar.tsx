import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ComponentProps } from 'react';
import { DayPicker, type DayButtonProps } from 'react-day-picker';

export type CalendarProps = ComponentProps<typeof DayPicker>;

export function Calendar({ className, classNames, showOutsideDays = true, ...props }: CalendarProps) {
    return (
        <DayPicker
            showOutsideDays={showOutsideDays}
            className={cn('p-3', className)}
            classNames={{
                root: 'w-fit',
                months: 'flex flex-col gap-4 sm:flex-row',
                month: 'flex flex-col gap-4',
                month_caption: 'flex items-center justify-center pt-1 relative',
                caption_label: 'text-sm font-medium',
                nav: 'flex items-center justify-between absolute inset-x-0 top-0',
                button_previous: cn(
                    buttonVariants({ variant: 'outline', size: 'icon-sm' }),
                    'absolute left-1 bg-transparent p-0 text-muted-foreground hover:text-foreground',
                ),
                button_next: cn(
                    buttonVariants({ variant: 'outline', size: 'icon-sm' }),
                    'absolute right-1 bg-transparent p-0 text-muted-foreground hover:text-foreground',
                ),
                month_grid: 'w-full border-collapse',
                weekdays: 'flex',
                weekday: 'w-9 text-xs font-normal text-muted-foreground flex-1',
                week: 'flex w-full mt-1',
                day: 'relative size-9 p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([data-selected=true])]:bg-accent',
                day_button: cn(buttonVariants({ variant: 'ghost' }), 'size-9 rounded-md p-0 font-normal aria-selected:opacity-100'),
                range_start: 'rounded-l-md bg-accent',
                range_middle: 'rounded-none',
                range_end: 'rounded-r-md bg-accent',
                selected:
                    'data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground data-[selected=true]:hover:bg-primary data-[selected=true]:hover:text-primary-foreground data-[selected=true]:focus:bg-primary data-[selected=true]:focus:text-primary-foreground [&>button]:bg-primary [&>button]:text-primary-foreground',
                today: 'bg-accent text-accent-foreground rounded-md',
                outside: 'text-muted-foreground aria-selected:text-muted-foreground opacity-50',
                disabled: 'text-muted-foreground opacity-50',
                hidden: 'invisible',
                ...classNames,
            }}
            components={{
                Chevron: ({ orientation, ...chevronProps }) =>
                    orientation === 'left' ? (
                        <ChevronLeft className="size-4" {...chevronProps} />
                    ) : (
                        <ChevronRight className="size-4" {...chevronProps} />
                    ),
                DayButton: ({ className: dayButtonClassName, day: _day, modifiers, ...dayButtonProps }: DayButtonProps) => (
                    <button
                        type="button"
                        data-selected={modifiers.selected || undefined}
                        className={cn(dayButtonClassName)}
                        {...dayButtonProps}
                    />
                ),
            }}
            {...props}
        />
    );
}
