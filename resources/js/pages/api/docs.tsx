import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ApiDocsPageProps, OpenApiOperation } from '@/types/api';
import { BookOpen, ExternalLink, Info, SearchX } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import { CopyButton } from './copy-button';

const METHOD_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    get: 'info',
    post: 'success',
    put: 'warning',
    patch: 'warning',
    delete: 'destructive',
};

interface Endpoint {
    path: string;
    method: string;
    operation: OpenApiOperation;
}

export default function ApiDocs({ spec, spec_url }: ApiDocsPageProps) {
    const searchId = useId();
    const [query, setQuery] = useState('');

    const server = spec.servers?.[0]?.url ?? '';

    const endpoints = useMemo<Endpoint[]>(() => {
        const list: Endpoint[] = [];

        for (const [path, operations] of Object.entries(spec.paths)) {
            for (const [method, operation] of Object.entries(operations)) {
                list.push({ path, method, operation });
            }
        }

        return list;
    }, [spec.paths]);

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return endpoints;
        }

        return endpoints.filter(
            (endpoint) =>
                endpoint.path.toLowerCase().includes(term) ||
                endpoint.method.toLowerCase().includes(term) ||
                (endpoint.operation.summary ?? '').toLowerCase().includes(term),
        );
    }, [endpoints, query]);

    const grouped = useMemo(() => {
        const map = new Map<string, Endpoint[]>();

        for (const endpoint of filtered) {
            const tag = endpoint.operation.tags?.[0] ?? 'General';
            map.set(tag, [...(map.get(tag) ?? []), endpoint]);
        }

        return [...map.entries()].sort(([a], [b]) => a.localeCompare(b));
    }, [filtered]);

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Developer' },
        { label: 'API docs' },
    ];

    return (
        <AppLayout title="API documentation" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title={spec.info.title}
                    description={spec.info.description ?? 'Generated from the routes this installation actually registers.'}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">OpenAPI {spec.openapi}</Badge>
                            <Badge variant="secondary">v{spec.info.version}</Badge>
                            <Button asChild variant="outline" size="sm">
                                <a href={spec_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="size-4" aria-hidden="true" />
                                    Raw spec
                                </a>
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Getting started</CardTitle>
                        <CardDescription>
                            Authenticate with a bearer token created under Developer → API tokens. A token is bound to one workspace and can
                            never read another.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Input readOnly aria-label="Base URL" value={server} className="font-mono text-xs" />
                            <CopyButton value={server} label="Copy base URL" className="shrink-0" />
                        </div>

                        <div className="overflow-x-auto rounded-md border border-border bg-muted/40 p-3">
                            <pre className="font-mono text-xs">
                                <code>{`curl ${server}/users \\\n  -H "Authorization: Bearer <token>" \\\n  -H "Accept: application/json"`}</code>
                            </pre>
                        </div>

                        <Alert>
                            <Info aria-hidden="true" />
                            <AlertDescription>
                                Collections return <code className="font-mono text-xs">{'{ data, meta, links }'}</code> and paginate by
                                cursor. Errors are <code className="font-mono text-xs">application/problem+json</code> documents (RFC 7807).
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                </Card>

                <div className="grid gap-2">
                    <label htmlFor={searchId} className="text-sm font-medium">
                        Filter endpoints
                    </label>
                    <Input
                        id={searchId}
                        type="search"
                        value={query}
                        placeholder="users, POST, list…"
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>

                {grouped.length === 0 ? (
                    <EmptyState
                        icon={query === '' ? BookOpen : SearchX}
                        title={query === '' ? 'No endpoints registered' : 'No endpoints match that filter'}
                        description={
                            query === ''
                                ? 'The public API exposes nothing in this build.'
                                : 'Try a resource name such as “users”, or an HTTP method.'
                        }
                    />
                ) : (
                    grouped.map(([tag, group]) => (
                        <Card key={tag}>
                            <CardHeader>
                                <CardTitle>{tag}</CardTitle>
                                <CardDescription>
                                    {group.length} endpoint{group.length === 1 ? '' : 's'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Accordion type="multiple" className="w-full">
                                    {group.map((endpoint) => (
                                        <AccordionItem
                                            key={`${endpoint.method}-${endpoint.path}`}
                                            value={`${endpoint.method}-${endpoint.path}`}
                                        >
                                            <AccordionTrigger>
                                                <span className="flex min-w-0 flex-wrap items-center gap-2 text-left">
                                                    <Badge
                                                        variant={METHOD_VARIANT[endpoint.method] ?? 'secondary'}
                                                        className="font-mono uppercase"
                                                    >
                                                        {endpoint.method}
                                                    </Badge>
                                                    <code className="min-w-0 font-mono text-xs break-all">{endpoint.path}</code>
                                                    <span className="text-xs text-muted-foreground">{endpoint.operation.summary}</span>
                                                </span>
                                            </AccordionTrigger>

                                            <AccordionContent className="space-y-4">
                                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                                    <Input
                                                        readOnly
                                                        aria-label={`Full URL for ${endpoint.method} ${endpoint.path}`}
                                                        value={`${server}${endpoint.path}`}
                                                        className="font-mono text-xs"
                                                    />
                                                    <CopyButton
                                                        value={`${server}${endpoint.path}`}
                                                        label="Copy URL"
                                                        size="icon-sm"
                                                        className="shrink-0"
                                                    />
                                                </div>

                                                {(endpoint.operation.parameters ?? []).length > 0 && (
                                                    <section className="space-y-2">
                                                        <h3 className="text-sm font-medium">Parameters</h3>
                                                        <ul className="space-y-2">
                                                            {(endpoint.operation.parameters ?? []).map((parameter) => (
                                                                <li key={`${parameter.in}-${parameter.name}`} className="min-w-0 text-sm">
                                                                    <span className="font-mono text-xs">{parameter.name}</span>{' '}
                                                                    <Badge variant="outline">{parameter.in}</Badge>{' '}
                                                                    {parameter.required && <Badge variant="secondary">required</Badge>}
                                                                    {parameter.description && (
                                                                        <span className="block text-xs text-muted-foreground">
                                                                            {parameter.description}
                                                                        </span>
                                                                    )}
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    </section>
                                                )}

                                                {endpoint.operation.requestBody && (
                                                    <p className="text-sm text-muted-foreground">Accepts a JSON request body.</p>
                                                )}

                                                <section className="space-y-2">
                                                    <h3 className="text-sm font-medium">Responses</h3>
                                                    <ul className="flex flex-wrap gap-2">
                                                        {Object.entries(endpoint.operation.responses ?? {}).map(([code, response]) => (
                                                            <li key={code}>
                                                                <Badge variant={code.startsWith('2') ? 'success' : 'outline'}>
                                                                    {code} · {response.description ?? ''}
                                                                </Badge>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </section>
                                            </AccordionContent>
                                        </AccordionItem>
                                    ))}
                                </Accordion>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
