import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';

export interface BulkAction<TRow> {
    id: string;
    label: string;
    icon?: ReactNode;
    variant?: 'default' | 'outline' | 'ghost' | 'destructive';
    onSelect: (rows: TRow[]) => void | Promise<void>;
}

export interface DataTableBulkActionsProps<TRow> {
    selectedRows: TRow[];
    actions: BulkAction<TRow>[];
    onClear: () => void;
    className?: string;
}

/** A floating bar that slides up while rows are selected. */
export function DataTableBulkActions<TRow>({ selectedRows, actions, onClear, className }: DataTableBulkActionsProps<TRow>) {
    const count = selectedRows.length;

    if (count === 0 || actions.length === 0) {
        return null;
    }

    return (
        <div
            role="region"
            aria-label="Bulk actions"
            className={cn(
                'fixed inset-x-0 bottom-4 z-40 mx-auto flex w-fit max-w-[calc(100vw-2rem)] items-center gap-2 rounded-full border border-border bg-popover px-3 py-2 shadow-lg',
                'animate-in duration-200 ease-out-quint fade-in slide-in-from-bottom-4',
                className,
            )}
        >
            <span className="px-2 text-sm font-medium whitespace-nowrap" aria-live="polite">
                {count} selected
            </span>
            <Separator orientation="vertical" className="h-5" />

            <div className="flex items-center gap-1 overflow-x-auto">
                {actions.map((action) => (
                    <Button
                        key={action.id}
                        variant={action.variant ?? 'ghost'}
                        size="sm"
                        className="whitespace-nowrap"
                        onClick={() => void action.onSelect(selectedRows)}
                    >
                        {action.icon}
                        {action.label}
                    </Button>
                ))}
            </div>

            <Separator orientation="vertical" className="h-5" />
            <Button variant="ghost" size="icon-sm" onClick={onClear} aria-label="Clear selection">
                <X className="size-4" aria-hidden="true" />
            </Button>
        </div>
    );
}
