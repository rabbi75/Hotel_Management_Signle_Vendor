import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { SeoContentRow, SeoDefaults, SitemapStatus } from '@/types/seo';
import { Link, router } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { ExternalLink, FileSearch, RefreshCw, Settings2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { useBlogPanel } from '../blog/use-panel';
import { useSeoPanel } from './use-panel';

interface SeoIndexProps {
    sitemap: SitemapStatus;
    defaults: SeoDefaults;
    content: SeoContentRow[];
    can: { update: boolean; generate: boolean };
}

export default function SeoIndex({ sitemap, defaults, content, can }: SeoIndexProps) {
    const { Layout, home, r, may } = useSeoPanel();
    const [regenerating, setRegenerating] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl(home) ?? undefined }, { label: 'SEO' }];

    const mayGenerate = can.generate && may('seo.sitemap.generate');

    function regenerate(): void {
        setRegenerating(true);

        router.post(
            r('sitemap.generate'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRegenerating(false),
            },
        );
    }

    return (
        <Layout title="SEO" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="SEO"
                    description="How this workspace describes itself to search engines, and what is stopping it from ranking."
                    actions={
                        can.update ? (
                            <Button variant="outline" asChild>
                                <Link href={r('settings.edit')}>
                                    <Settings2 className="size-4" aria-hidden="true" />
                                    Defaults
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                {!defaults.indexable && (
                    <Card className="border-warning/40 bg-warning/5">
                        <CardContent className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="flex items-start gap-2 text-sm">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden="true" />
                                <span>
                                    <strong>This site is not indexable.</strong> robots.txt currently answers{' '}
                                    <code className="rounded bg-muted px-1 py-0.5 text-xs">Disallow: /</code>, so nothing here will be
                                    crawled. That is the safe default for staging — turn it on when you go live.
                                </span>
                            </p>
                            {can.update && (
                                <Button size="sm" asChild className="shrink-0">
                                    <Link href={r('settings.edit')}>Review defaults</Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Sitemap</CardTitle>
                            <CardDescription>The map crawlers use to discover your pages.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {sitemap.exists ? (
                                <dl className="space-y-2 text-sm">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <dt className="text-muted-foreground">URLs</dt>
                                        <dd className="font-medium tabular-nums">{sitemap.url_count ?? 0}</dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-3">
                                        <dt className="text-muted-foreground">Generated</dt>
                                        <dd className="font-medium">
                                            {sitemap.generated_at
                                                ? `${formatDistanceToNow(parseISO(sitemap.generated_at))} ago`
                                                : 'Unknown'}
                                        </dd>
                                    </div>
                                    <div className="flex items-baseline justify-between gap-3">
                                        <dt className="text-muted-foreground">Location</dt>
                                        <dd className="min-w-0 truncate">
                                            <a
                                                href={sitemap.url}
                                                className="inline-flex items-center gap-1 rounded-sm underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                /{sitemap.path}
                                                <ExternalLink className="size-3" aria-hidden="true" />
                                            </a>
                                        </dd>
                                    </div>
                                </dl>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No sitemap has been generated yet. Crawlers can still find pages by following links, but a sitemap is
                                    how they find the ones nothing links to.
                                </p>
                            )}

                            {mayGenerate && (
                                <Button variant="outline" onClick={regenerate} disabled={regenerating} className="w-full">
                                    <RefreshCw className={cn('size-4', regenerating && 'animate-spin')} aria-hidden="true" />
                                    {regenerating ? 'Regenerating…' : 'Regenerate now'}
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Content health</CardTitle>
                            <CardDescription>Weakest pages first. Open one to see exactly which checks it fails.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {content.length === 0 ? (
                                <EmptyState
                                    icon={FileSearch}
                                    title="Nothing to analyse yet"
                                    description="Publish a post or a page and its SEO checks will appear here."
                                    className="border-0"
                                />
                            ) : (
                                <ul className="divide-y divide-border">
                                    {content.map((row) => (
                                        <ContentRow key={`${row.type}-${row.id}`} row={row} />
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Layout>
    );
}

function ContentRow({ row }: { row: SeoContentRow }) {
    const { r: blogRoute } = useBlogPanel();
    const href = row.type === 'post' ? blogRoute('posts.edit', row.id) : null;

    const tone = row.failed > 0 ? 'text-destructive' : row.score >= 80 ? 'text-success' : 'text-warning';

    return (
        <li className="flex flex-wrap items-center gap-3 py-3">
            <div className="min-w-0 flex-1">
                {href ? (
                    <Link
                        href={href}
                        className="block truncate text-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {row.title}
                    </Link>
                ) : (
                    <p className="truncate text-sm font-medium">{row.title}</p>
                )}
                <p className="text-xs text-muted-foreground">
                    {row.type_label}
                    {!row.has_meta && ' · never edited'}
                </p>
            </div>

            <div className="flex w-32 shrink-0 items-center gap-2">
                <Progress value={row.score} className="h-1.5" aria-label={`Score ${row.score} of 100`} />
                <span className={cn('w-8 shrink-0 text-right text-sm font-medium tabular-nums', tone)}>{row.score}</span>
            </div>

            {row.failed > 0 && (
                <Badge variant="destructive" className="shrink-0">
                    {row.failed} to fix
                </Badge>
            )}
        </li>
    );
}
