import { Icon } from '@/components/app-shell/icon';
import { routeUrl } from '@/components/app-shell/routing';
import { useDebouncedValue } from '@/components/app-shell/use-debounced-value';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import { EmptyState } from '@/components/ui/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useCommands } from '@/hooks/use-command-registry';
import { useUiStore } from '@/stores/ui-store';
import type { NavItem, SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { SearchX } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { groupCommands } from './command-registry';

const SEARCH_DEBOUNCE_MS = 250;
const MIN_SEARCH_LENGTH = 2;

interface SearchResult {
    id: string;
    title: string;
    subtitle?: string | null;
    url: string;
    icon?: string | null;
}

interface SearchGroup {
    label: string;
    items: SearchResult[];
}

const GROUP_LABELS: Record<string, string> = {
    users: 'Users',
    workspaces: 'Workspaces',
    settings: 'Settings',
    pages: 'Pages',
};

const GROUP_ICONS: Record<string, string> = {
    users: 'user-round',
    workspaces: 'building-2',
    settings: 'settings',
    pages: 'file-text',
};

/**
 * Accepts either `{ groups: [...] }` or a bare `{ users: [], pages: [] }` map,
 * so the palette keeps working whichever shape the search controller settles on.
 */
function normaliseResults(payload: unknown): SearchGroup[] {
    if (typeof payload !== 'object' || payload === null) {
        return [];
    }

    const record = payload as Record<string, unknown>;

    if (Array.isArray(record.groups)) {
        return record.groups.filter(isSearchGroup);
    }

    const groups: SearchGroup[] = [];

    for (const [key, value] of Object.entries(record)) {
        if (!Array.isArray(value) || value.length === 0) {
            continue;
        }

        const items = value.filter(isSearchResult).map((item) => ({ ...item, icon: item.icon ?? GROUP_ICONS[key] ?? null }));

        if (items.length > 0) {
            groups.push({ label: GROUP_LABELS[key] ?? key, items });
        }
    }

    return groups;
}

function isSearchResult(value: unknown): value is SearchResult {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return typeof record.title === 'string' && typeof record.url === 'string';
}

function isSearchGroup(value: unknown): value is SearchGroup {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return typeof record.label === 'string' && Array.isArray(record.items) && record.items.every(isSearchResult);
}

interface FlatNavItem {
    label: string;
    href: string;
    icon: string | null;
    section: string;
    /** Parent labels, so `Settings › Members` disambiguates repeated child names. */
    trail: string;
}

function flattenNavigation(sections: SharedProps['navigation']): FlatNavItem[] {
    const flat: FlatNavItem[] = [];

    function walk(items: NavItem[], section: string, trail: string[]): void {
        for (const item of items) {
            if (item.href) {
                flat.push({
                    label: item.label,
                    href: item.href,
                    icon: item.icon,
                    section,
                    trail: [...trail, item.label].join(' › '),
                });
            }

            if (item.children.length > 0) {
                walk(item.children, section, [...trail, item.label]);
            }
        }
    }

    for (const section of sections) {
        walk(section.items, section.label, section.label ? [section.label] : []);
    }

    return flat;
}

export function CommandPalette() {
    const { navigation } = usePage<SharedProps>().props;
    const open = useUiStore((state) => state.commandPaletteOpen);
    const setOpen = useUiStore((state) => state.setCommandPaletteOpen);
    const commands = useCommands();

    const [query, setQuery] = useState('');
    const debouncedQuery = useDebouncedValue(query.trim(), SEARCH_DEBOUNCE_MS);

    const navItems = useMemo(() => flattenNavigation(navigation), [navigation]);
    const grouped = useMemo(() => groupCommands(commands), [commands]);

    const searchEndpoint = routeUrl('search.index');
    const searchEnabled = open && searchEndpoint !== null && debouncedQuery.length >= MIN_SEARCH_LENGTH;

    const { data, isFetching } = useQuery({
        queryKey: ['command-palette-search', debouncedQuery],
        enabled: searchEnabled,
        queryFn: async ({ signal }): Promise<SearchGroup[]> => {
            const url = new URL(searchEndpoint ?? '', window.location.origin);
            url.searchParams.set('q', debouncedQuery);

            const response = await fetch(url.toString(), {
                signal,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                throw new Error(`Search failed with status ${response.status}`);
            }

            return normaliseResults(await response.json());
        },
    });

    const results = data ?? [];

    const close = useCallback(() => {
        setOpen(false);
        setQuery('');
    }, [setOpen]);

    const go = useCallback(
        (href: string) => {
            close();
            router.visit(href);
        },
        [close],
    );

    const run = useCallback(
        (perform: () => void) => {
            close();
            perform();
        },
        [close],
    );

    const showSkeleton = searchEnabled && isFetching && results.length === 0;
    const hasAnything = navItems.length > 0 || commands.length > 0 || results.length > 0;

    return (
        <CommandDialog
            open={open}
            onOpenChange={(next) => (next ? setOpen(true) : close())}
            title="Command palette"
            description="Search for a page, run an action, or jump to a record."
        >
            <CommandInput value={query} onValueChange={setQuery} placeholder="Type a command or search…" />

            <CommandList>
                {!showSkeleton && (
                    <CommandEmpty>
                        <EmptyState
                            icon={SearchX}
                            title="No matches"
                            description="Try a different term, or press Escape to close."
                            className="border-0 py-6"
                        />
                    </CommandEmpty>
                )}

                {showSkeleton && (
                    <div className="space-y-2 p-3" aria-live="polite" aria-busy="true">
                        <span className="sr-only">Searching…</span>
                        {[0, 1, 2].map((index) => (
                            <div key={index} className="flex items-center gap-3">
                                <Skeleton className="size-8 rounded-md" />
                                <div className="flex-1 space-y-1.5">
                                    <Skeleton className="h-3 w-1/3" />
                                    <Skeleton className="h-3 w-1/2" />
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {navItems.length > 0 && (
                    <CommandGroup heading="Navigation">
                        {navItems.map((item) => (
                            <CommandItem
                                key={`${item.section}-${item.trail}`}
                                value={`nav ${item.trail} ${item.label}`}
                                onSelect={() => go(item.href)}
                            >
                                <Icon name={item.icon} className="size-4 opacity-70" />
                                <span className="flex-1 truncate">{item.label}</span>
                                {item.section && <CommandShortcut>{item.section}</CommandShortcut>}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {grouped.map(([name, items]) => (
                    <CommandGroup key={name} heading={name}>
                        {items.map((command) => (
                            <CommandItem
                                key={command.id}
                                value={`${name} ${command.label} ${(command.keywords ?? []).join(' ')}`}
                                onSelect={() => run(command.perform)}
                            >
                                <Icon name={command.icon} className="size-4 opacity-70" />
                                <span className="flex-1 truncate">
                                    {command.label}
                                    {command.subtitle && (
                                        <span className="block truncate text-xs text-muted-foreground">{command.subtitle}</span>
                                    )}
                                </span>
                                {command.shortcut && <CommandShortcut>{command.shortcut.join(' ')}</CommandShortcut>}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                ))}

                {results.map((group) => (
                    <CommandGroup key={group.label} heading={group.label}>
                        {group.items.map((item) => (
                            <CommandItem
                                key={`${group.label}-${item.id}`}
                                value={`${group.label} ${item.title} ${item.subtitle ?? ''}`}
                                onSelect={() => go(item.url)}
                            >
                                <Icon name={item.icon} className="size-4 opacity-70" />
                                <span className="flex-1 truncate">
                                    {item.title}
                                    {item.subtitle && <span className="block truncate text-xs text-muted-foreground">{item.subtitle}</span>}
                                </span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                ))}

                {hasAnything && <CommandSeparator />}
            </CommandList>
        </CommandDialog>
    );
}
