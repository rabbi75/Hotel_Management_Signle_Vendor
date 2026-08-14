import { cn } from '@/lib/utils';
import type { TableColumn } from '@/types';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';

export interface DataTableColumnHeaderProps {
    column: TableColumn;
    sort: string | null;
    direction: 'asc' | 'desc';
    onToggle: (key: string) => void;
}

const ALIGN: Record<TableColumn['align'], string> = {
    left: 'justify-start text-left',
    center: 'justify-center text-center',
    right: 'justify-end text-right',
};

export function DataTableColumnHeader({ column, sort, direction, onToggle }: DataTableColumnHeaderProps) {
    if (!column.sortable) {
        return <span className={cn('flex items-center', ALIGN[column.align])}>{column.label}</span>;
    }

    const active = sort === column.key;
    const nextLabel = !active ? 'ascending' : direction === 'asc' ? 'descending' : 'no order';
    const Indicator = !active ? ChevronsUpDown : direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <button
            type="button"
            onClick={() => onToggle(column.key)}
            className={cn(
                'group -mx-2 flex w-[calc(100%+1rem)] items-center gap-1.5 rounded-sm px-2 py-1 font-medium transition-colors',
                'hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                ALIGN[column.align],
                active && 'text-foreground',
            )}
            aria-label={`${column.label}, sort by ${nextLabel}`}
        >
            <span className="truncate">{column.label}</span>
            <Indicator
                className={cn('size-3.5 shrink-0 transition-opacity', active ? 'opacity-100' : 'opacity-0 group-hover:opacity-60')}
                aria-hidden="true"
            />
        </button>
    );
}
