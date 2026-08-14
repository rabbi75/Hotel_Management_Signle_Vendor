import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { TableMeta } from '@/types';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { useId } from 'react';

export interface DataTablePaginationProps {
    meta: TableMeta;
    perPageOptions: number[];
    onPerPageChange: (value: number) => void;
    onPageChange: (page: number) => void;
    selectedCount: number;
}

export function DataTablePagination({ meta, perPageOptions, onPerPageChange, onPageChange, selectedCount }: DataTablePaginationProps) {
    const perPageId = useId();
    const first = meta.current_page <= 1;
    const last = meta.current_page >= meta.last_page;

    return (
        <div className="flex flex-col gap-3 border-t border-border px-1 pt-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground" aria-live="polite">
                {meta.total === 0 ? (
                    'No results'
                ) : (
                    <>
                        Showing <span className="font-medium text-foreground">{meta.from ?? 0}</span>–
                        <span className="font-medium text-foreground">{meta.to ?? 0}</span> of{' '}
                        <span className="font-medium text-foreground">{meta.total}</span>
                        {selectedCount > 0 && <span className="ml-2">· {selectedCount} selected</span>}
                    </>
                )}
            </p>

            <div className="flex flex-wrap items-center gap-4">
                <div className="flex items-center gap-2">
                    <Label htmlFor={perPageId} className="text-sm font-normal whitespace-nowrap text-muted-foreground">
                        Rows per page
                    </Label>
                    <Select value={String(meta.per_page)} onValueChange={(value) => onPerPageChange(Number(value))}>
                        <SelectTrigger id={perPageId} className="h-8 w-[4.5rem]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {perPageOptions.map((option) => (
                                <SelectItem key={option} value={String(option)}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <nav aria-label="Pagination" className="flex items-center gap-1">
                    <span className="mr-2 text-sm whitespace-nowrap text-muted-foreground">
                        Page {meta.current_page} of {Math.max(meta.last_page, 1)}
                    </span>
                    <Button variant="outline" size="icon-sm" disabled={first} onClick={() => onPageChange(1)} aria-label="First page">
                        <ChevronsLeft className="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        disabled={first}
                        onClick={() => onPageChange(meta.current_page - 1)}
                        aria-label="Previous page"
                    >
                        <ChevronLeft className="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        disabled={last}
                        onClick={() => onPageChange(meta.current_page + 1)}
                        aria-label="Next page"
                    >
                        <ChevronRight className="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        disabled={last}
                        onClick={() => onPageChange(meta.last_page)}
                        aria-label="Last page"
                    >
                        <ChevronsRight className="size-4" aria-hidden="true" />
                    </Button>
                </nav>
            </div>
        </div>
    );
}
