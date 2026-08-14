import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import type { BulkAction } from '@/components/data-table/data-table-bulk-actions';
import { EnumBadgeCell, RelativeDateCell, TextCell, type BadgeVariant, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { BlogComment, CommentStatus } from '@/types/blog';
import { router } from '@inertiajs/react';
import { Ban, Check, MessageSquare, Trash2, Undo2 } from 'lucide-react';
import { useBlogPanel } from '../use-panel';

interface CommentsIndexProps {
    table: TablePayload<BlogComment>;
    counts: Record<CommentStatus, number>;
}

const STATUS_BADGES: Record<CommentStatus, { label: string; variant: BadgeVariant }> = {
    pending: { label: 'Pending', variant: 'warning' },
    approved: { label: 'Approved', variant: 'success' },
    spam: { label: 'Spam', variant: 'destructive' },
};

export default function CommentsIndex({ table, counts }: CommentsIndexProps) {
    const { Layout, home, r } = useBlogPanel();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl(home) ?? undefined },
        { label: 'Posts', href: r('posts.index') },
        { label: 'Comments' },
    ];

    function moderate(action: 'approve' | 'spam' | 'pending' | 'delete', ids: number[]): void {
        router.post(r('comments.moderate'), { action, ids }, { preserveScroll: true });
    }

    async function confirmDelete(count: number): Promise<boolean> {
        return confirm({
            title: count === 1 ? 'Delete this comment?' : `Delete ${count} comments?`,
            description: 'Deleting is permanent. Marking as spam keeps the record and hides it from readers.',
            variant: 'destructive',
            confirmLabel: 'Delete',
        });
    }

    const columns: ColumnRenderers<BlogComment> = {
        author: (row) => (
            <div className="min-w-0">
                <span className="block truncate text-sm font-medium">{row.author}</span>
                <span className="block truncate text-xs text-muted-foreground">
                    {row.author_email ?? '—'}
                    {row.is_guest && ' · guest'}
                </span>
            </div>
        ),
        // Rendered as text, never as HTML: comments have no markup path at all.
        body: (row) => <p className="line-clamp-3 max-w-md text-sm break-words">{row.body}</p>,
        post: (row) => <TextCell value={row.post} muted />,
        status: (row) => <EnumBadgeCell value={row.status} map={STATUS_BADGES} />,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
        ip_address: (row) => <TextCell value={row.ip_address} muted />,
    };

    function rowActions(row: BlogComment): RowAction[] {
        const actions: RowAction[] = [];

        if (row.status !== 'approved') {
            actions.push({
                id: 'approve',
                label: 'Approve',
                icon: <Check className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => moderate('approve', [row.id]),
            });
        }

        if (row.status !== 'spam') {
            actions.push({
                id: 'spam',
                label: 'Mark as spam',
                icon: <Ban className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => moderate('spam', [row.id]),
            });
        }

        if (row.status !== 'pending') {
            actions.push({
                id: 'pending',
                label: 'Send back to pending',
                icon: <Undo2 className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => moderate('pending', [row.id]),
            });
        }

        actions.push({
            id: 'delete',
            label: 'Delete',
            destructive: true,
            separatorBefore: true,
            icon: <Trash2 className="size-4" aria-hidden="true" />,
            onSelect: () => {
                void confirmDelete(1).then((ok) => {
                    if (ok) {
                        moderate('delete', [row.id]);
                    }
                });
            },
        });

        return actions;
    }

    const bulkActions: BulkAction<BlogComment>[] = [
        {
            id: 'approve',
            label: 'Approve',
            icon: <Check className="size-4" aria-hidden="true" />,
            onSelect: (rows: BlogComment[]) =>
                moderate(
                    'approve',
                    rows.map((row) => row.id),
                ),
        },
        {
            id: 'spam',
            label: 'Mark as spam',
            icon: <Ban className="size-4" aria-hidden="true" />,
            onSelect: (rows: BlogComment[]) =>
                moderate(
                    'spam',
                    rows.map((row) => row.id),
                ),
        },
        {
            id: 'delete',
            label: 'Delete',
            variant: 'destructive',
            icon: <Trash2 className="size-4" aria-hidden="true" />,
            onSelect: async (rows: BlogComment[]) => {
                if (await confirmDelete(rows.length)) {
                    moderate(
                        'delete',
                        rows.map((row) => row.id),
                    );
                }
            },
        },
    ];

    return (
        <Layout title="Comments" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Comments"
                    description="Nothing a reader submits appears on the site until it is approved here."
                    actions={
                        <span className="flex flex-wrap items-center gap-2">
                            <Badge variant="warning">{counts.pending ?? 0} pending</Badge>
                            <Badge variant="success">{counts.approved ?? 0} approved</Badge>
                            <Badge variant="secondary">{counts.spam ?? 0} spam</Badge>
                        </span>
                    }
                />

                <DataTable<BlogComment>
                    payload={table}
                    propKey="table"
                    name="comments"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    selectable
                    bulkActions={bulkActions}
                    rowActions={rowActions}
                    primaryColumn="body"
                    searchPlaceholder="Search comments…"
                    caption="Comments awaiting moderation"
                    emptyState={
                        <EmptyState
                            icon={MessageSquare}
                            title="Nothing to moderate"
                            description="New comments land here as soon as a reader submits one."
                            className="border-0"
                        />
                    }
                />
            </div>
        </Layout>
    );
}
