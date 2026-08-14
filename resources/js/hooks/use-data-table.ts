import type { TableColumn, TableFilter, TableMeta, TablePayload, TableState } from '@/types';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

export const SEARCH_DEBOUNCE_MS = 300;

export type SortDirection = 'asc' | 'desc';

export interface TableQueryState {
    search: string | null;
    sort: string | null;
    direction: SortDirection;
    perPage: number | null;
    page: number;
    filters: Record<string, unknown>;
}

/**
 * The query-string key a table uses for `suffix`.
 *
 * Mirrors `TableBuilder::key()`: the default table is unprefixed so URLs stay
 * readable, and any other table namespaces its keys so two can share a page.
 */
export function tableKey(name: string, suffix: string): string {
    return name === 'table' ? suffix : `${name}_${suffix}`;
}

function isEmptyValue(value: unknown): boolean {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    if (typeof value === 'object') {
        return Object.values(value as Record<string, unknown>).every((entry) => entry === null || entry === undefined || entry === '');
    }

    return false;
}

function appendFilter(params: URLSearchParams, base: string, value: unknown): void {
    if (Array.isArray(value)) {
        for (const entry of value) {
            params.append(`${base}[]`, String(entry));
        }

        return;
    }

    if (typeof value === 'boolean') {
        params.set(base, value ? '1' : '0');

        return;
    }

    if (value !== null && typeof value === 'object') {
        for (const [key, entry] of Object.entries(value as Record<string, unknown>)) {
            if (entry !== null && entry !== undefined && entry !== '') {
                params.append(`${base}[${key}]`, String(entry));
            }
        }

        return;
    }

    params.set(base, String(value));
}

/**
 * Rewrites only this table's parameters inside `currentSearch`, leaving every
 * unrelated query parameter — other tables, feature flags, UTM tags — untouched.
 */
export function buildTableQuery(currentSearch: string, name: string, state: TableQueryState): string {
    const params = new URLSearchParams(currentSearch);
    const key = (suffix: string) => tableKey(name, suffix);
    const filterPrefix = key('filters');

    for (const suffix of ['search', 'sort', 'direction', 'per_page']) {
        params.delete(key(suffix));
    }

    params.delete(key('page'));

    for (const existing of [...params.keys()]) {
        if (existing === filterPrefix || existing.startsWith(`${filterPrefix}[`)) {
            params.delete(existing);
        }
    }

    if (state.search) {
        params.set(key('search'), state.search);
    }

    if (state.sort) {
        params.set(key('sort'), state.sort);
        params.set(key('direction'), state.direction);
    }

    if (state.perPage) {
        params.set(key('per_page'), String(state.perPage));
    }

    if (state.page > 1) {
        params.set(key('page'), String(state.page));
    }

    for (const [filterKey, value] of Object.entries(state.filters)) {
        if (isEmptyValue(value)) {
            continue;
        }

        appendFilter(params, `${filterPrefix}[${filterKey}]`, value);
    }

    return params.toString();
}

/** asc → desc → unsorted, the cycle a header click walks through. */
export function nextSort(
    column: string,
    current: { sort: string | null; direction: SortDirection },
): { sort: string | null; direction: SortDirection } {
    if (current.sort !== column) {
        return { sort: column, direction: 'asc' };
    }

    if (current.direction === 'asc') {
        return { sort: column, direction: 'desc' };
    }

    return { sort: null, direction: 'asc' };
}

export interface UseDataTableOptions<TRow> {
    payload: TablePayload<TRow>;
    /** The Inertia prop key holding this payload; partial reloads request only it. */
    propKey: string;
    /** Must match the `$name` given to `TableBuilder::for()`. */
    name?: string;
    /** Stable row identity for selection. Defaults to the row's `id`. */
    getRowId?: (row: TRow) => string;
}

export interface UseDataTableResult<TRow> {
    rows: TRow[];
    meta: TableMeta;
    columns: TableColumn[];
    filters: TableFilter[];
    serverState: TableState;

    search: string;
    setSearch: (value: string) => void;

    sort: string | null;
    direction: SortDirection;
    toggleSort: (column: string) => void;

    filterValues: Record<string, unknown>;
    setFilter: (key: string, value: unknown) => void;
    clearFilters: () => void;
    activeFilterCount: number;

    perPage: number;
    setPerPage: (value: number) => void;
    perPageOptions: number[];

    page: number;
    setPage: (value: number) => void;

    loading: boolean;

    visibleColumns: TableColumn[];
    isColumnVisible: (key: string) => boolean;
    toggleColumn: (key: string, visible: boolean) => void;
    resetColumns: () => void;

    rowId: (row: TRow) => string;
    selected: string[];
    isSelected: (row: TRow) => boolean;
    toggleRow: (row: TRow, checked: boolean) => void;
    toggleAll: (checked: boolean) => void;
    clearSelection: () => void;
    allSelected: boolean;
    someSelected: boolean;
    selectedRows: TRow[];
}

function defaultRowId<TRow>(row: TRow): string {
    const record = row as unknown as Record<string, unknown>;
    const candidate = record.id ?? record.uuid ?? record.key;

    return String(candidate ?? JSON.stringify(row));
}

function hiddenStorageKey(name: string): string {
    return `datatable:${name}:hidden`;
}

function readHiddenColumns(name: string): string[] {
    try {
        const raw = window.localStorage.getItem(hiddenStorageKey(name));
        const parsed: unknown = raw ? JSON.parse(raw) : null;

        return Array.isArray(parsed) ? parsed.filter((entry): entry is string => typeof entry === 'string') : [];
    } catch {
        return [];
    }
}

/**
 * Owns every piece of table state that lives in the URL.
 *
 * Each change is a partial Inertia visit scoped to `propKey`, so sorting a
 * table never re-renders — or re-queries — the rest of the page.
 */
export function useDataTable<TRow>({ payload, propKey, name = 'table', getRowId }: UseDataTableOptions<TRow>): UseDataTableResult<TRow> {
    const { rows, meta, columns, filters, state, per_page_options: perPageOptions } = payload;

    const [search, setSearchState] = useState(state.search ?? '');
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    const [hidden, setHidden] = useState<string[]>(() => readHiddenColumns(name));

    const debounceTimer = useRef<number | null>(null);
    // The freshest server state, read inside callbacks so a visit never
    // resurrects a value the previous visit already changed.
    const latest = useRef({ state, meta });
    latest.current = { state, meta };

    useEffect(() => {
        setSearchState(state.search ?? '');
    }, [state.search]);

    // A new page of rows invalidates ids that are no longer on screen.
    useEffect(() => {
        setSelected([]);
    }, [meta.current_page, state.search, state.sort, state.direction]);

    const visit = useCallback(
        (next: Partial<TableQueryState>) => {
            const current = latest.current;
            const params = new URLSearchParams(window.location.search);
            const read = (suffix: string) => params.get(tableKey(name, suffix));

            // The baseline comes from the URL, not from the echoed server state:
            // `state` also reports the server's *defaults* for sort, direction
            // and page size, and writing those back would pin a default the user
            // never chose into every subsequent link.
            const target: TableQueryState = {
                search: read('search'),
                sort: read('sort'),
                direction: read('direction') === 'desc' ? 'desc' : 'asc',
                perPage: read('per_page') === null ? null : Number(read('per_page')),
                page: current.meta.current_page,
                filters: current.state.filters,
                ...next,
            };

            const query = buildTableQuery(window.location.search, name, target);

            router.get(`${window.location.pathname}${query ? `?${query}` : ''}`, undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: [propKey],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        },
        [name, propKey],
    );

    const setSearch = useCallback(
        (value: string) => {
            setSearchState(value);

            if (debounceTimer.current !== null) {
                window.clearTimeout(debounceTimer.current);
            }

            debounceTimer.current = window.setTimeout(() => {
                debounceTimer.current = null;
                visit({ search: value.trim() === '' ? null : value, page: 1 });
            }, SEARCH_DEBOUNCE_MS);
        },
        [visit],
    );

    useEffect(
        () => () => {
            if (debounceTimer.current !== null) {
                window.clearTimeout(debounceTimer.current);
            }
        },
        [],
    );

    const toggleSort = useCallback(
        (column: string) => {
            const current = latest.current.state;
            const next = nextSort(column, { sort: current.sort, direction: current.direction });

            visit({ ...next, page: 1 });
        },
        [visit],
    );

    const setFilter = useCallback(
        (key: string, value: unknown) => {
            const nextFilters = { ...latest.current.state.filters };

            if (isEmptyValue(value)) {
                delete nextFilters[key];
            } else {
                nextFilters[key] = value;
            }

            visit({ filters: nextFilters, page: 1 });
        },
        [visit],
    );

    const clearFilters = useCallback(() => visit({ filters: {}, search: null, page: 1 }), [visit]);
    const setPerPage = useCallback((value: number) => visit({ perPage: value, page: 1 }), [visit]);
    const setPage = useCallback((value: number) => visit({ page: value }), [visit]);

    const toggleColumn = useCallback(
        (key: string, visible: boolean) => {
            setHidden((current) => {
                const next = visible ? current.filter((entry) => entry !== key) : [...new Set([...current, key])];

                try {
                    window.localStorage.setItem(hiddenStorageKey(name), JSON.stringify(next));
                } catch {
                    // A blocked or full localStorage must not break the table.
                }

                return next;
            });
        },
        [name],
    );

    const resetColumns = useCallback(() => {
        setHidden([]);

        try {
            window.localStorage.removeItem(hiddenStorageKey(name));
        } catch {
            // Ignored — see above.
        }
    }, [name]);

    const isColumnVisible = useCallback(
        (key: string) => {
            const column = columns.find((entry) => entry.key === key);

            if (!column) {
                return false;
            }

            if (!column.toggleable) {
                return !column.hidden;
            }

            return !hidden.includes(key);
        },
        [columns, hidden],
    );

    const visibleColumns = useMemo(
        () => columns.filter((column) => (column.toggleable ? !hidden.includes(column.key) : !column.hidden)),
        [columns, hidden],
    );

    const rowId = useCallback((row: TRow) => (getRowId ? getRowId(row) : defaultRowId(row)), [getRowId]);

    const isSelected = useCallback((row: TRow) => selected.includes(rowId(row)), [selected, rowId]);

    const toggleRow = useCallback(
        (row: TRow, checked: boolean) => {
            const id = rowId(row);

            setSelected((current) => (checked ? [...new Set([...current, id])] : current.filter((entry) => entry !== id)));
        },
        [rowId],
    );

    const toggleAll = useCallback((checked: boolean) => setSelected(checked ? rows.map((row) => rowId(row)) : []), [rows, rowId]);

    const clearSelection = useCallback(() => setSelected([]), []);

    const selectedRows = useMemo(() => rows.filter((row) => selected.includes(rowId(row))), [rows, selected, rowId]);
    const activeFilterCount = useMemo(() => Object.values(state.filters).filter((value) => !isEmptyValue(value)).length, [state.filters]);

    return {
        rows,
        meta,
        columns,
        filters,
        serverState: state,

        search,
        setSearch,

        sort: state.sort,
        direction: state.direction,
        toggleSort,

        filterValues: state.filters,
        setFilter,
        clearFilters,
        activeFilterCount,

        perPage: state.per_page,
        setPerPage,
        perPageOptions,

        page: meta.current_page,
        setPage,

        loading,

        visibleColumns,
        isColumnVisible,
        toggleColumn,
        resetColumns,

        rowId,
        selected,
        isSelected,
        toggleRow,
        toggleAll,
        clearSelection,
        allSelected: rows.length > 0 && selected.length === rows.length,
        someSelected: selected.length > 0 && selected.length < rows.length,
        selectedRows,
    };
}
