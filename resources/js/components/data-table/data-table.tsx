import { Checkbox } from '@/components/ui/checkbox';
import { EmptyState } from '@/components/ui/empty-state';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/hooks/use-data-table';
import { cn } from '@/lib/utils';
import type { TableColumn, TablePayload } from '@/types';
import { SearchX } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import { DataTableBulkActions, type BulkAction } from './data-table-bulk-actions';
import { RowActionsCell, type RowAction } from './data-table-cells';
import { DataTableColumnHeader } from './data-table-column-header';
import { DataTableFilterBar } from './data-table-filter-bar';
import { DataTablePagination } from './data-table-pagination';
import { DataTableCardSkeleton, DataTableSkeleton } from './data-table-skeleton';
import { DataTableViewOptions, type ExportFormat } from './data-table-view-options';

export type ColumnRenderers<TRow> = Record<string, (row: TRow) => ReactNode>;

export interface DataTableProps<TRow> {
    payload: TablePayload<TRow>;
    /** The Inertia prop key holding `payload`; partial reloads request only it. */
    propKey: string;
    /** Must match the `$name` passed to `TableBuilder::for()`. */
    name?: string;
    /** Cell renderers keyed by column key. A missing key falls back to the raw value. */
    columns: ColumnRenderers<TRow>;
    getRowId?: (row: TRow) => string;
    selectable?: boolean;
    bulkActions?: BulkAction<TRow>[];
    rowActions?: (row: TRow) => RowAction[];
    /** Base URL for CSV/Excel export; the current filters are appended. */
    exportUrl?: string;
    onExport?: (format: ExportFormat) => void;
    onRowClick?: (row: TRow) => void;
    searchPlaceholder?: string;
    /** Column key used as the card title on narrow screens. Defaults to the first column. */
    primaryColumn?: string;
    emptyState?: ReactNode;
    /** Extra controls rendered at the right of the filter bar. */
    toolbar?: ReactNode;
    caption?: string;
    className?: string;
}

const ALIGN: Record<TableColumn['align'], string> = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};

function rawValue<TRow>(row: TRow, key: string): ReactNode {
    const value = (row as unknown as Record<string, unknown>)[key];

    if (value === null || value === undefined || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

export function DataTable<TRow>({
    payload,
    propKey,
    name = 'table',
    columns,
    getRowId,
    selectable = false,
    bulkActions = [],
    rowActions,
    exportUrl,
    onExport,
    onRowClick,
    searchPlaceholder,
    primaryColumn,
    emptyState,
    toolbar,
    caption,
    className,
}: DataTableProps<TRow>) {
    const table = useDataTable<TRow>({ payload, propKey, name, ...(getRowId ? { getRowId } : {}) });

    const {
        rows,
        meta,
        filters,
        visibleColumns,
        loading,
        search,
        setSearch,
        sort,
        direction,
        toggleSort,
        filterValues,
        setFilter,
        clearFilters,
        activeFilterCount,
        isColumnVisible,
        toggleColumn,
        resetColumns,
        rowId,
        isSelected,
        toggleRow,
        toggleAll,
        clearSelection,
        allSelected,
        someSelected,
        selectedRows,
        selected,
    } = table;

    const hasRowActions = typeof rowActions === 'function';
    const columnCount = visibleColumns.length + (selectable ? 1 : 0) + (hasRowActions ? 1 : 0);
    const titleKey = primaryColumn ?? visibleColumns[0]?.key ?? null;
    const titleColumn = visibleColumns.find((column) => column.key === titleKey) ?? visibleColumns[0] ?? null;

    const handleExport = useMemo(() => {
        if (onExport) {
            return onExport;
        }

        if (!exportUrl) {
            return undefined;
        }

        return (format: ExportFormat) => {
            if (format === 'print') {
                window.print();

                return;
            }

            const url = new URL(exportUrl, window.location.origin);

            for (const [key, value] of new URLSearchParams(window.location.search)) {
                url.searchParams.append(key, value);
            }

            url.searchParams.set('format', format);
            window.location.assign(url.toString());
        };
    }, [exportUrl, onExport]);

    const headerCheckboxState: boolean | 'indeterminate' = allSelected ? true : someSelected ? 'indeterminate' : false;

    function renderCell(row: TRow, column: TableColumn): ReactNode {
        const renderer = columns[column.key];

        return renderer ? renderer(row) : rawValue(row, column.key);
    }

    const empty = rows.length === 0 && !loading;

    return (
        <div className={cn('space-y-4', className)}>
            <DataTableFilterBar
                filters={filters}
                values={filterValues}
                onChange={setFilter}
                onClear={clearFilters}
                search={search}
                onSearchChange={setSearch}
                {...(searchPlaceholder ? { searchPlaceholder } : {})}
                activeCount={activeFilterCount}
            >
                {toolbar}
                <DataTableViewOptions
                    columns={payload.columns}
                    isColumnVisible={isColumnVisible}
                    onToggleColumn={toggleColumn}
                    onReset={resetColumns}
                    {...(handleExport ? { onExport: handleExport } : {})}
                />
            </DataTableFilterBar>

            <p className="sr-only" role="status" aria-live="polite">
                {loading
                    ? 'Loading results'
                    : `${meta.total} result${meta.total === 1 ? '' : 's'}, page ${meta.current_page} of ${meta.last_page}`}
            </p>

            {/* Desktop: a real table with a sticky header. */}
            <div className="hidden overflow-hidden rounded-lg border border-border md:block">
                <div className="max-h-[70svh] overflow-auto">
                    <Table>
                        {caption && <caption className="sr-only">{caption}</caption>}
                        <TableHeader sticky>
                            <TableRow>
                                {selectable && (
                                    <TableHead className="w-10">
                                        <Checkbox
                                            checked={headerCheckboxState}
                                            onCheckedChange={(checked) => toggleAll(checked === true)}
                                            aria-label={allSelected ? 'Clear selection' : 'Select all rows on this page'}
                                            disabled={rows.length === 0}
                                        />
                                    </TableHead>
                                )}

                                {visibleColumns.map((column) => (
                                    <TableHead
                                        key={column.key}
                                        style={column.width ? { width: column.width } : undefined}
                                        aria-sort={sort === column.key ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}
                                        className={ALIGN[column.align]}
                                    >
                                        <DataTableColumnHeader column={column} sort={sort} direction={direction} onToggle={toggleSort} />
                                    </TableHead>
                                ))}

                                {hasRowActions && (
                                    <TableHead className="w-12 text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>

                        {loading && rows.length === 0 ? (
                            <DataTableSkeleton columns={Math.max(columnCount, 1)} />
                        ) : (
                            <TableBody className={cn(loading && 'opacity-60 transition-opacity')}>
                                {rows.map((row) => {
                                    const id = rowId(row);
                                    const checked = isSelected(row);

                                    return (
                                        <TableRow
                                            key={id}
                                            data-state={checked ? 'selected' : undefined}
                                            className={cn(onRowClick && 'cursor-pointer')}
                                            onClick={onRowClick ? () => onRowClick(row) : undefined}
                                        >
                                            {selectable && (
                                                <TableCell onClick={(event) => event.stopPropagation()}>
                                                    <Checkbox
                                                        checked={checked}
                                                        onCheckedChange={(next) => toggleRow(row, next === true)}
                                                        aria-label={`Select row ${id}`}
                                                    />
                                                </TableCell>
                                            )}

                                            {visibleColumns.map((column) => (
                                                <TableCell key={column.key} className={ALIGN[column.align]}>
                                                    {renderCell(row, column)}
                                                </TableCell>
                                            ))}

                                            {hasRowActions && (
                                                <TableCell className="text-right" onClick={(event) => event.stopPropagation()}>
                                                    <RowActionsCell actions={rowActions(row)} />
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        )}
                    </Table>
                </div>

                {empty && (
                    <div className="p-6">
                        {emptyState ?? (
                            <EmptyState
                                icon={SearchX}
                                title="Nothing to show"
                                description={
                                    activeFilterCount > 0 || search
                                        ? 'No records match the current filters.'
                                        : 'Records will appear here once they exist.'
                                }
                                className="border-0"
                            />
                        )}
                    </div>
                )}
            </div>

            {/* Mobile: stacked cards. Horizontal scrolling a wide table is unusable on a phone. */}
            <div className="md:hidden">
                {loading && rows.length === 0 ? (
                    <DataTableCardSkeleton />
                ) : empty ? (
                    (emptyState ?? (
                        <EmptyState
                            icon={SearchX}
                            title="Nothing to show"
                            description={
                                activeFilterCount > 0 || search
                                    ? 'No records match the current filters.'
                                    : 'Records will appear here once they exist.'
                            }
                        />
                    ))
                ) : (
                    <ul className={cn('space-y-3', loading && 'opacity-60 transition-opacity')}>
                        {rows.map((row) => {
                            const id = rowId(row);
                            const checked = isSelected(row);

                            return (
                                <li key={id} className="rounded-lg border border-border bg-card p-4 shadow-xs">
                                    <div className="flex items-start gap-3">
                                        {selectable && (
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(next) => toggleRow(row, next === true)}
                                                aria-label={`Select row ${id}`}
                                                className="mt-0.5"
                                            />
                                        )}

                                        <div className="min-w-0 flex-1 space-y-2">
                                            {titleColumn && <div className="text-sm font-medium">{renderCell(row, titleColumn)}</div>}

                                            <dl className="grid gap-1.5">
                                                {visibleColumns
                                                    .filter((column) => column.key !== titleColumn?.key)
                                                    .map((column) => (
                                                        <div key={column.key} className="flex items-baseline justify-between gap-3">
                                                            <dt className="text-xs text-muted-foreground">{column.label}</dt>
                                                            <dd className="min-w-0 text-right text-sm">{renderCell(row, column)}</dd>
                                                        </div>
                                                    ))}
                                            </dl>
                                        </div>

                                        {hasRowActions && <RowActionsCell actions={rowActions(row)} />}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <DataTablePagination
                meta={meta}
                perPageOptions={payload.per_page_options}
                onPerPageChange={table.setPerPage}
                onPageChange={table.setPage}
                selectedCount={selected.length}
            />

            {selectable && <DataTableBulkActions selectedRows={selectedRows} actions={bulkActions} onClear={clearSelection} />}
        </div>
    );
}
