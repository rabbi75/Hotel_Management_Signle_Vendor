import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { format, formatDistanceToNow, isValid, parseISO } from 'date-fns';
import { Check, Minus, MoreHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';

export function TextCell({ value, className, muted = false }: { value: ReactNode; className?: string; muted?: boolean }) {
    return <span className={cn('block truncate', muted && 'text-muted-foreground', className)}>{value ?? '—'}</span>;
}

export type BadgeVariant = NonNullable<BadgeProps['variant']>;

export interface EnumBadgeCellProps {
    value: string | null | undefined;
    /** Maps the raw enum value to a label and a badge variant. */
    map: Record<string, { label: string; variant?: BadgeVariant }>;
    fallbackVariant?: BadgeVariant;
}

export function EnumBadgeCell({ value, map, fallbackVariant = 'secondary' }: EnumBadgeCellProps) {
    if (!value) {
        return <TextCell value="—" muted />;
    }

    const entry = map[value];

    return <Badge variant={entry?.variant ?? fallbackVariant}>{entry?.label ?? value}</Badge>;
}

export interface AvatarCellProps {
    name: string;
    subtitle?: string | null;
    src?: string | null;
    initials?: string | null;
}

export function AvatarCell({ name, subtitle, src, initials }: AvatarCellProps) {
    const fallback = initials ?? name.slice(0, 2).toUpperCase();

    return (
        <div className="flex min-w-0 items-center gap-2">
            <Avatar size="sm">
                {src && <AvatarImage src={src} alt="" />}
                <AvatarFallback className="text-xs">{fallback}</AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <span className="block truncate text-sm font-medium">{name}</span>
                {subtitle && <span className="block truncate text-xs text-muted-foreground">{subtitle}</span>}
            </div>
        </div>
    );
}

function toDate(value: string | number | Date | null | undefined): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const date = value instanceof Date ? value : typeof value === 'number' ? new Date(value) : parseISO(value);

    return isValid(date) ? date : null;
}

/** Relative time with the absolute timestamp in a tooltip and in `<time datetime>`. */
export function RelativeDateCell({ value }: { value: string | number | Date | null | undefined }) {
    const date = toDate(value);

    if (!date) {
        return <TextCell value="—" muted />;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <time dateTime={date.toISOString()} className="text-sm whitespace-nowrap text-muted-foreground">
                    {formatDistanceToNow(date, { addSuffix: true })}
                </time>
            </TooltipTrigger>
            <TooltipContent>{format(date, 'PPpp')}</TooltipContent>
        </Tooltip>
    );
}

export function DateCell({ value, pattern = 'PP' }: { value: string | number | Date | null | undefined; pattern?: string }) {
    const date = toDate(value);

    if (!date) {
        return <TextCell value="—" muted />;
    }

    return (
        <time dateTime={date.toISOString()} className="text-sm whitespace-nowrap">
            {format(date, pattern)}
        </time>
    );
}

/** A tick or dash, never colour alone — the icon carries the meaning. */
export function BooleanCell({ value, trueLabel = 'Yes', falseLabel = 'No' }: { value: boolean; trueLabel?: string; falseLabel?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-sm', value ? 'text-success' : 'text-muted-foreground')}>
            {value ? <Check className="size-4" aria-hidden="true" /> : <Minus className="size-4" aria-hidden="true" />}
            <span className="sr-only sm:not-sr-only">{value ? trueLabel : falseLabel}</span>
        </span>
    );
}

export interface CurrencyCellProps {
    /** Minor units when `minorUnits` is true (the usual Laravel money column). */
    value: number | string | null | undefined;
    currency?: string;
    locale?: string;
    minorUnits?: boolean;
}

export function CurrencyCell({ value, currency = 'USD', locale, minorUnits = false }: CurrencyCellProps) {
    if (value === null || value === undefined || value === '') {
        return <TextCell value="—" muted />;
    }

    const numeric = typeof value === 'number' ? value : Number(value);

    if (Number.isNaN(numeric)) {
        return <TextCell value="—" muted />;
    }

    const amount = minorUnits ? numeric / 100 : numeric;

    return (
        <span className="block text-sm font-medium tabular-nums">
            {new Intl.NumberFormat(locale, { style: 'currency', currency }).format(amount)}
        </span>
    );
}

export interface RowAction {
    id: string;
    label: string;
    icon?: ReactNode;
    destructive?: boolean;
    disabled?: boolean;
    onSelect: () => void | Promise<void>;
    /** Starts a new group above this item. */
    separatorBefore?: boolean;
}

export function RowActionsCell({ actions, label = 'Row actions' }: { actions: RowAction[]; label?: string }) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label={label}>
                    <MoreHorizontal className="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {actions.map((action) => (
                    <div key={action.id}>
                        {action.separatorBefore && <DropdownMenuSeparator />}
                        <DropdownMenuItem
                            disabled={action.disabled}
                            variant={action.destructive ? 'destructive' : 'default'}
                            onSelect={() => void action.onSelect()}
                        >
                            {action.icon}
                            {action.label}
                        </DropdownMenuItem>
                    </div>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
