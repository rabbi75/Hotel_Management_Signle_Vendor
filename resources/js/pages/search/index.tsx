import { Icon } from '@/components/app-shell/icon';
import { PageHeader } from '@/components/app-shell/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { Search as SearchIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

interface SearchResultItem {
    id: string;
    title: string;
    subtitle: string | null;
    icon: string | null;
    url: string | null;
    group: string;
}

interface SearchResultGroup {
    key: string;
    label: string;
    icon: string;
    items: SearchResultItem[];
}

interface SearchProviderSummary {
    key: string;
    label: string;
    icon: string;
}

interface SearchPageProps {
    results: {
        term: string;
        total: number;
        groups: SearchResultGroup[];
    };
    providers: SearchProviderSummary[];
    min_length: number;
}

export default function SearchIndex({ results, providers, min_length: minLength }: SearchPageProps) {
    const [term, setTerm] = useState(results.term);
    const inputRef = useRef<HTMLInputElement>(null);

    // The results page is usually reached from the command palette, so the user
    // is already mid-thought — put the caret where they expect it.
    useEffect(() => {
        inputRef.current?.focus();
        inputRef.current?.setSelectionRange(term.length, term.length);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const activeProviders = useMemo(() => {
        const params = new URLSearchParams(window.location.search);

        return params.getAll('in[]').length > 0 ? params.getAll('in[]') : params.getAll('in');
    }, []);

    function submit(nextTerm: string, only: string[] = activeProviders): void {
        router.get(
            route('search.index'),
            { q: nextTerm, ...(only.length > 0 ? { in: only } : {}) },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleProvider(key: string): void {
        const next = activeProviders.includes(key) ? activeProviders.filter((item) => item !== key) : [...activeProviders, key];

        submit(term, next);
    }

    const tooShort = term.trim().length > 0 && term.trim().length < minLength;

    return (
        <AppLayout breadcrumbs={[{ label: 'Search' }]}>
            <Head title={results.term ? `Search — ${results.term}` : 'Search'} />

            <PageHeader
                title="Search"
                description={
                    results.term
                        ? `${results.total} ${results.total === 1 ? 'result' : 'results'} for “${results.term}”`
                        : 'Search across everything you have access to.'
                }
            />

            <form
                className="flex flex-col gap-3 sm:flex-row sm:items-center"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit(term);
                }}
            >
                <div className="relative flex-1">
                    <Icon
                        name="search"
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        ref={inputRef}
                        value={term}
                        onChange={(event) => setTerm(event.target.value)}
                        placeholder="Search users, workspaces, settings…"
                        className="pl-9"
                        aria-label="Search"
                        aria-describedby={tooShort ? 'search-hint' : undefined}
                        aria-invalid={tooShort}
                    />
                </div>
                <Button type="submit" disabled={tooShort}>
                    Search
                </Button>
            </form>

            {providers.length > 1 && (
                <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Filter by type">
                    {providers.map((provider) => {
                        const active = activeProviders.includes(provider.key);

                        return (
                            <button
                                key={provider.key}
                                type="button"
                                onClick={() => toggleProvider(provider.key)}
                                aria-pressed={active}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                    active
                                        ? 'border-transparent bg-primary text-primary-foreground'
                                        : 'border-border bg-card text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                )}
                            >
                                <Icon name={provider.icon} className="size-3.5" aria-hidden />
                                {provider.label}
                            </button>
                        );
                    })}
                </div>
            )}

            {tooShort && (
                <p id="search-hint" className="text-sm text-muted-foreground" aria-live="polite">
                    Enter at least {minLength} characters to search.
                </p>
            )}

            <div aria-live="polite" className="flex flex-col gap-6">
                {results.groups.length === 0 ? (
                    <EmptyState
                        icon={SearchIcon}
                        title={results.term ? 'No matches' : 'Start typing to search'}
                        description={
                            results.term
                                ? `Nothing matched “${results.term}”. Try a different term, or clear the type filters.`
                                : 'Results from every area you can access will appear here.'
                        }
                    />
                ) : (
                    results.groups.map((group) => (
                        <Card key={group.key}>
                            <CardContent className="p-0">
                                <div className="flex items-center gap-2 px-4 py-3">
                                    <Icon name={group.icon} className="size-4 text-muted-foreground" aria-hidden />
                                    <h2 className="text-sm font-medium">{group.label}</h2>
                                    <Badge variant="secondary">{group.items.length}</Badge>
                                </div>
                                <Separator />
                                <ul className="divide-y divide-border">
                                    {group.items.map((item) => (
                                        <li key={`${group.key}-${item.id}`}>
                                            <ResultRow item={item} />
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </AppLayout>
    );
}

function ResultRow({ item }: { item: SearchResultItem }) {
    const body = (
        <>
            <Icon name={item.icon ?? 'file-text'} className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden />
            <span className="min-w-0">
                <span className="block truncate text-sm font-medium">{item.title}</span>
                {item.subtitle && <span className="block truncate text-sm text-muted-foreground">{item.subtitle}</span>}
            </span>
        </>
    );

    // Providers may return an entry the user cannot open — a settings key, or a
    // record whose detail route is gated. Render it, but not as a dead link.
    if (item.url === null) {
        return <div className="flex items-start gap-3 px-4 py-3">{body}</div>;
    }

    return (
        <Link
            href={item.url}
            className="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
        >
            {body}
        </Link>
    );
}
