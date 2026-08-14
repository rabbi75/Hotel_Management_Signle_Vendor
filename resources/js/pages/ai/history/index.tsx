import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AiGenerationRow, AiHistoryPageProps } from '@/types/ai';
import { router } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { CircleAlert, SearchX, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CopyButton } from '../../api/copy-button';

const STATUS_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    success: 'success',
    destructive: 'destructive',
    warning: 'warning',
    muted: 'secondary',
};

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PPpp') : '—';
}

export default function AiHistoryIndex({ table }: AiHistoryPageProps) {
    const confirm = useConfirm();
    const [selected, setSelected] = useState<AiGenerationRow | null>(null);

    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: AiGenerationRow): Promise<void> {
        const ok = await confirm({
            title: 'Delete this generation?',
            description: 'The prompt, the output and the token counts are removed. Credits already charged are not refunded.',
            variant: 'destructive',
            confirmLabel: 'Delete generation',
        });

        if (ok) {
            setSelected(null);
            router.delete(route('ai.history.destroy', row.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<AiGenerationRow> = {
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
        provider: (row) => <Badge variant="outline">{row.provider}</Badge>,
        model: (row) => <code className="block truncate font-mono text-xs">{row.model}</code>,
        input: (row) => <TextCell value={row.input} className="max-w-[24rem]" />,
        status: (row) => <Badge variant={STATUS_VARIANT[row.status_color] ?? 'secondary'}>{row.status_label}</Badge>,
        total_tokens: (row) => <span className="tabular-nums">{row.total_tokens}</span>,
        credits_charged: (row) => <span className="tabular-nums">{row.credits_charged}</span>,
        user: (row) => <TextCell value={row.user} muted />,
    };

    function rowActions(row: AiGenerationRow): RowAction[] {
        return [
            { id: 'inspect', label: 'View details', onSelect: () => setSelected(row) },
            {
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            },
        ];
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'AI', href: routeUrl('ai.index') ?? undefined },
        { label: 'History' },
    ];

    return (
        <AppLayout title="Generation history" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="Generation history" description="Every AI run in this workspace, with the tokens and credits it cost." />

                <DataTable<AiGenerationRow>
                    payload={table}
                    propKey="table"
                    name="generations"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    onRowClick={(row) => setSelected(row)}
                    searchPlaceholder="Search prompts…"
                    caption="AI generation history"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No generations match these filters"
                                description="Widen the date range or clear the provider filter."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={Sparkles}
                                title="No generations yet"
                                description="Runs from the playground appear here."
                                className="border-0"
                            />
                        )
                    }
                />
            </div>

            <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>Generation {selected?.id}</SheetTitle>
                        <SheetDescription>
                            {selected?.provider} · {selected?.model}
                        </SheetDescription>
                    </SheetHeader>

                    {selected && (
                        <div className="space-y-5 px-4 pb-6">
                            <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                                <div>
                                    <dt className="text-muted-foreground">Status</dt>
                                    <dd>
                                        <Badge variant={STATUS_VARIANT[selected.status_color] ?? 'secondary'}>
                                            {selected.status_label}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Stop reason</dt>
                                    <dd className="font-mono text-xs">{selected.stop_reason ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Duration</dt>
                                    <dd className="tabular-nums">{selected.duration_ms} ms</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Prompt tokens</dt>
                                    <dd className="tabular-nums">{selected.prompt_tokens}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Output tokens</dt>
                                    <dd className="tabular-nums">{selected.completion_tokens}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Credits</dt>
                                    <dd className="tabular-nums">{selected.credits_charged}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Template</dt>
                                    <dd>{selected.template ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">User</dt>
                                    <dd>{selected.user ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">When</dt>
                                    <dd>{absolute(selected.created_at)}</dd>
                                </div>
                            </dl>

                            {selected.error && (
                                <Alert variant="destructive">
                                    <CircleAlert aria-hidden="true" />
                                    <AlertTitle>This generation failed</AlertTitle>
                                    <AlertDescription className="break-words">{selected.error}</AlertDescription>
                                </Alert>
                            )}

                            <section className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="text-sm font-medium">Prompt</h3>
                                    <CopyButton value={selected.input} label="Copy prompt" size="icon-sm" />
                                </div>
                                <div className="max-h-56 overflow-auto rounded-md border border-border bg-muted/40 p-3">
                                    <p className="text-sm whitespace-pre-wrap">{selected.input}</p>
                                </div>
                            </section>

                            <section className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="text-sm font-medium">Output</h3>
                                    <CopyButton
                                        value={selected.output ?? ''}
                                        label="Copy output"
                                        size="icon-sm"
                                        disabled={!selected.output}
                                    />
                                </div>
                                <div className="max-h-72 overflow-auto rounded-md border border-border bg-muted/40 p-3">
                                    {selected.output ? (
                                        <p className="text-sm whitespace-pre-wrap">{selected.output}</p>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No output was produced — the model refused or the call failed.
                                        </p>
                                    )}
                                </div>
                            </section>

                            <Button type="button" variant="outline" onClick={() => void remove(selected)}>
                                <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                                Delete generation
                            </Button>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
