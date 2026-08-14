import type { TablePayload } from '@/types';
import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { buildTableQuery, nextSort, SEARCH_DEBOUNCE_MS, tableKey, useDataTable, type TableQueryState } from './use-data-table';

const visit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: {
        get: (...args: unknown[]) => visit(...args),
    },
}));

interface Row {
    id: number;
    name: string;
}

function state(overrides: Partial<TableQueryState> = {}): TableQueryState {
    return {
        search: null,
        sort: null,
        direction: 'asc',
        perPage: null,
        page: 1,
        filters: {},
        ...overrides,
    };
}

function payload(overrides: Partial<TablePayload<Row>> = {}): TablePayload<Row> {
    return {
        rows: [
            { id: 1, name: 'Ada' },
            { id: 2, name: 'Grace' },
        ],
        meta: { current_page: 1, last_page: 3, per_page: 15, total: 42, from: 1, to: 2 },
        columns: [
            { key: 'name', label: 'Name', sortable: true, hidden: false, toggleable: true, align: 'left', width: null },
            { key: 'email', label: 'Email', sortable: false, hidden: false, toggleable: true, align: 'left', width: null },
        ],
        filters: [],
        state: { search: null, sort: null, direction: 'asc', per_page: 15, filters: {} },
        per_page_options: [15, 25, 50],
        ...overrides,
    };
}

function lastUrl(): string {
    const call = visit.mock.calls.at(-1);

    return typeof call?.[0] === 'string' ? call[0] : '';
}

function lastOptions(): Record<string, unknown> {
    const call = visit.mock.calls.at(-1);

    return (call?.[2] ?? {}) as Record<string, unknown>;
}

beforeEach(() => {
    visit.mockClear();
    window.history.replaceState({}, '', '/users');
    window.localStorage.clear();
});

describe('tableKey', () => {
    it('leaves the default table unprefixed and namespaces every other table', () => {
        expect(tableKey('table', 'search')).toBe('search');
        expect(tableKey('members', 'search')).toBe('members_search');
    });
});

describe('nextSort', () => {
    it('cycles a column through ascending, descending, then unsorted', () => {
        const first = nextSort('name', { sort: null, direction: 'asc' });
        expect(first).toEqual({ sort: 'name', direction: 'asc' });

        const second = nextSort('name', first);
        expect(second).toEqual({ sort: 'name', direction: 'desc' });

        const third = nextSort('name', second);
        expect(third.sort).toBeNull();
    });

    it('restarts at ascending when a different column is clicked', () => {
        expect(nextSort('email', { sort: 'name', direction: 'desc' })).toEqual({ sort: 'email', direction: 'asc' });
    });
});

describe('buildTableQuery', () => {
    it('preserves query parameters that belong to something else', () => {
        const query = buildTableQuery('tab=members&utm_source=email&page=4', 'table', state({ search: 'ada' }));
        const params = new URLSearchParams(query);

        expect(params.get('tab')).toBe('members');
        expect(params.get('utm_source')).toBe('email');
        expect(params.get('search')).toBe('ada');
    });

    it('drops its own parameters when they return to the default', () => {
        const query = buildTableQuery('search=old&sort=name&direction=desc&per_page=50&page=3&keep=1', 'table', state());
        const params = new URLSearchParams(query);

        expect(params.get('keep')).toBe('1');
        expect(params.has('search')).toBe(false);
        expect(params.has('sort')).toBe(false);
        expect(params.has('direction')).toBe(false);
        expect(params.has('per_page')).toBe(false);
        expect(params.has('page')).toBe(false);
    });

    it('omits page one and writes direction only alongside a sort', () => {
        expect(new URLSearchParams(buildTableQuery('', 'table', state({ page: 1 }))).has('page')).toBe(false);
        expect(new URLSearchParams(buildTableQuery('', 'table', state({ page: 2 }))).get('page')).toBe('2');

        const sorted = new URLSearchParams(buildTableQuery('', 'table', state({ sort: 'name', direction: 'desc' })));
        expect(sorted.get('sort')).toBe('name');
        expect(sorted.get('direction')).toBe('desc');
    });

    it('namespaces every key for a named table and leaves the other table alone', () => {
        const query = buildTableQuery('search=keep-me&filters[status]=active', 'members', state({ search: 'ada', sort: 'name' }));
        const params = new URLSearchParams(query);

        expect(params.get('members_search')).toBe('ada');
        expect(params.get('members_sort')).toBe('name');
        expect(params.get('search')).toBe('keep-me');
        expect(params.get('filters[status]')).toBe('active');
    });

    describe('filter serialisation', () => {
        it('writes a scalar filter as filters[key]', () => {
            const params = new URLSearchParams(buildTableQuery('', 'table', state({ filters: { status: 'active' } })));

            expect(params.get('filters[status]')).toBe('active');
        });

        it('writes an array filter as repeated filters[key][] entries', () => {
            const params = new URLSearchParams(buildTableQuery('', 'table', state({ filters: { role: ['admin', 'member'] } })));

            expect(params.getAll('filters[role][]')).toEqual(['admin', 'member']);
        });

        it('writes a boolean filter as 1 or 0', () => {
            expect(new URLSearchParams(buildTableQuery('', 'table', state({ filters: { verified: true } }))).get('filters[verified]')).toBe(
                '1',
            );
            expect(
                new URLSearchParams(buildTableQuery('', 'table', state({ filters: { verified: false } }))).get('filters[verified]'),
            ).toBe('0');
        });

        it('expands a date range into from and to keys, skipping blank ends', () => {
            const params = new URLSearchParams(
                buildTableQuery('', 'table', state({ filters: { created: { from: '2026-01-01', to: '' } } })),
            );

            expect(params.get('filters[created][from]')).toBe('2026-01-01');
            expect(params.has('filters[created][to]')).toBe(false);
        });

        it('omits empty values entirely and clears filters left over in the URL', () => {
            const params = new URLSearchParams(
                buildTableQuery('filters[status]=active&filters[role][]=admin', 'table', state({ filters: { status: '', role: [] } })),
            );

            expect(params.has('filters[status]')).toBe(false);
            expect(params.getAll('filters[role][]')).toEqual([]);
        });
    });
});

describe('useDataTable', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('debounces search and issues a single scoped partial visit', () => {
        const { result } = renderHook(() => useDataTable<Row>({ payload: payload(), propKey: 'users' }));

        act(() => {
            result.current.setSearch('a');
            result.current.setSearch('ad');
            result.current.setSearch('ada');
        });

        expect(result.current.search).toBe('ada');
        expect(visit).not.toHaveBeenCalled();

        act(() => {
            vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS);
        });

        expect(visit).toHaveBeenCalledTimes(1);
        expect(lastUrl()).toBe('/users?search=ada');
        expect(lastOptions()).toMatchObject({ preserveState: true, preserveScroll: true, replace: true, only: ['users'] });
    });

    it('clears the search parameter when the box is emptied', () => {
        const { result } = renderHook(() =>
            useDataTable<Row>({
                payload: payload({ state: { search: 'ada', sort: null, direction: 'asc', per_page: 15, filters: {} } }),
                propKey: 'users',
            }),
        );

        act(() => {
            result.current.setSearch('');
            vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS);
        });

        expect(lastUrl()).toBe('/users');
    });

    it('walks a header click through ascending, descending and back to unsorted', () => {
        const first = payload();
        const { result, rerender } = renderHook(
            (props: { payload: TablePayload<Row> }) => useDataTable<Row>({ payload: props.payload, propKey: 'users' }),
            {
                initialProps: { payload: first },
            },
        );

        act(() => result.current.toggleSort('name'));
        expect(lastUrl()).toBe('/users?sort=name&direction=asc');

        rerender({ payload: payload({ state: { search: null, sort: 'name', direction: 'asc', per_page: 15, filters: {} } }) });
        act(() => result.current.toggleSort('name'));
        expect(lastUrl()).toBe('/users?sort=name&direction=desc');

        rerender({ payload: payload({ state: { search: null, sort: 'name', direction: 'desc', per_page: 15, filters: {} } }) });
        act(() => result.current.toggleSort('name'));
        expect(lastUrl()).toBe('/users');
    });

    it('keeps unrelated query parameters across a state change', () => {
        window.history.replaceState({}, '', '/users?tab=archived&utm_source=email');

        const { result } = renderHook(() => useDataTable<Row>({ payload: payload(), propKey: 'users' }));

        act(() => result.current.setPage(3));

        const params = new URLSearchParams(lastUrl().split('?')[1]);
        expect(params.get('tab')).toBe('archived');
        expect(params.get('utm_source')).toBe('email');
        expect(params.get('page')).toBe('3');
    });

    it('resets to the first page when a filter or page size changes', () => {
        window.history.replaceState({}, '', '/users?page=5');

        const { result } = renderHook(() => useDataTable<Row>({ payload: payload(), propKey: 'users' }));

        act(() => result.current.setFilter('status', 'active'));
        expect(new URLSearchParams(lastUrl().split('?')[1]).has('page')).toBe(false);

        act(() => result.current.setPerPage(50));
        const params = new URLSearchParams(lastUrl().split('?')[1]);
        expect(params.get('per_page')).toBe('50');
        expect(params.has('page')).toBe(false);
    });

    it('tracks row selection and reports the indeterminate middle state', () => {
        const { result } = renderHook(() => useDataTable<Row>({ payload: payload(), propKey: 'users' }));

        act(() => result.current.toggleRow({ id: 1, name: 'Ada' }, true));

        expect(result.current.someSelected).toBe(true);
        expect(result.current.allSelected).toBe(false);
        expect(result.current.selectedRows).toHaveLength(1);

        act(() => result.current.toggleAll(true));
        expect(result.current.allSelected).toBe(true);

        act(() => result.current.clearSelection());
        expect(result.current.selected).toEqual([]);
    });

    it('hides a toggled-off column and remembers it', () => {
        const { result } = renderHook(() => useDataTable<Row>({ payload: payload(), propKey: 'users', name: 'users' }));

        act(() => result.current.toggleColumn('email', false));

        expect(result.current.isColumnVisible('email')).toBe(false);
        expect(result.current.visibleColumns.map((column) => column.key)).toEqual(['name']);
        expect(window.localStorage.getItem('datatable:users:hidden')).toBe('["email"]');

        act(() => result.current.resetColumns());
        expect(result.current.isColumnVisible('email')).toBe(true);
    });
});
