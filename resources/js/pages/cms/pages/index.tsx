import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import type { BreadcrumbItem } from '@/types';
import type { PageRow, PagesIndexProps } from '@/types/cms';
import { Link, router } from '@inertiajs/react';
import { Copy, ExternalLink, FileText, House, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';
import { usePanel } from './use-panel';

const STATUS_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    published: 'success',
    scheduled: 'warning',
    draft: 'secondary',
};

export default function CmsPagesIndex({ table, can }: PagesIndexProps) {
    const { r, may, Layout, home, scopeLabel } = usePanel();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl(home) ?? undefined }, { label: 'Pages' }];

    const mayUpdate = may('update');
    const mayDelete = may('delete') && can.delete;
    const mayCreate = may('create') && can.create;
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const columns: ColumnRenderers<PageRow> = {
        title: (row) => (
            <span className="flex min-w-0 items-center gap-2">
                {row.is_homepage && <House className="size-3.5 shrink-0 text-muted-foreground" aria-label="Homepage" />}
                {mayUpdate ? (
                    <Link
                        href={r('pages.edit', row.id)}
                        className="truncate font-medium text-foreground underline-offset-4 hover:underline"
                    >
                        {row.title}
                    </Link>
                ) : (
                    <span className="truncate font-medium text-foreground">{row.title}</span>
                )}
            </span>
        ),
        slug: (row) => <TextCell value={row.path} className="font-mono text-xs" />,
        status: (row) => <Badge variant={STATUS_VARIANT[row.status] ?? 'secondary'}>{row.status_label}</Badge>,
        parent: (row) => <TextCell value={row.parent} muted />,
        blocks_count: (row) => <TextCell value={row.blocks_count === null ? '—' : String(row.blocks_count)} />,
        published_at: (row) => <DateCell value={row.published_at} />,
        updated_at: (row) => <DateCell value={row.updated_at} />,
    };

    function rowActions(row: PageRow): RowAction[] {
        const actions: RowAction[] = [];

        if (mayUpdate) {
            actions.push({
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(r('pages.edit', row.id)),
            });
        }

        if (row.is_public) {
            actions.push({
                id: 'view',
                label: 'View live',
                icon: <ExternalLink className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(row.path),
            });
        }

        if (mayCreate) {
            actions.push({
                id: 'duplicate',
                label: 'Duplicate',
                separatorBefore: true,
                icon: <Copy className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.post(r('pages.duplicate', row.id), {}, { preserveScroll: true }),
            });
        }

        if (mayDelete) {
            actions.push({
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: async () => {
                    const ok = await confirm({
                        title: `Delete “${row.title}”?`,
                        description: 'The page and its blocks are removed from the site. An administrator can restore it.',
                        variant: 'destructive',
                        confirmLabel: 'Delete page',
                    });

                    if (ok) {
                        router.delete(r('pages.destroy', row.id), { preserveScroll: true });
                    }
                },
            });
        }

        return actions;
    }

    return (
        <Layout title="Pages" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Pages"
                    description="The public pages of your site, built from reusable content blocks."
                    actions={
                        mayCreate ? (
                            <Button asChild>
                                <Link href={r('pages.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New page
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <DataTable<PageRow>
                    payload={table}
                    propKey="table"
                    name="pages"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search pages…"
                    caption={scopeLabel}
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : FileText}
                            title={filtered ? 'No pages match those filters' : 'No pages yet'}
                            description={
                                filtered ? 'Try clearing the search or filters.' : 'Create your first page to start building the site.'
                            }
                            className="border-0"
                            action={
                                mayCreate && !filtered ? (
                                    <Button asChild size="sm">
                                        <Link href={r('pages.create')}>
                                            <Plus className="size-4" aria-hidden="true" />
                                            New page
                                        </Link>
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>
        </Layout>
    );
}
