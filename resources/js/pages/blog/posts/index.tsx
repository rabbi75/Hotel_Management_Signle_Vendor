import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, EnumBadgeCell, TextCell, type BadgeVariant, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { Post, PostStatus } from '@/types/blog';
import { Link, router } from '@inertiajs/react';
import { BookOpen, Copy, ExternalLink, Eye, EyeOff, Pencil, Plus, SearchX, Send, Trash2 } from 'lucide-react';
import { useBlogPanel } from '../use-panel';

interface PostsIndexProps {
    table: TablePayload<Post>;
    can: { create: boolean; publish: boolean; delete: boolean };
}

const STATUS_BADGES: Record<PostStatus, { label: string; variant: BadgeVariant }> = {
    draft: { label: 'Draft', variant: 'secondary' },
    scheduled: { label: 'Scheduled', variant: 'info' },
    published: { label: 'Published', variant: 'success' },
    archived: { label: 'Archived', variant: 'warning' },
};

export default function PostsIndex({ table, can }: PostsIndexProps) {
    const { Layout, home, r, may } = useBlogPanel();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl(home) ?? undefined }, { label: 'Posts' }];

    const mayUpdate = may('blog.posts.update');
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(post: Post): Promise<void> {
        const ok = await confirm({
            title: `Delete “${post.title}”?`,
            description: post.is_published
                ? 'This post is live. Deleting it turns its URL into a 404 for anyone holding a link to it.'
                : 'The post moves to the trash and disappears from the list.',
            variant: 'destructive',
            confirmLabel: 'Delete post',
        });

        if (ok) {
            router.delete(r('posts.destroy', post.id), { preserveScroll: true });
        }
    }

    async function unpublish(post: Post): Promise<void> {
        const ok = await confirm({
            title: `Unpublish “${post.title}”?`,
            description: 'It becomes a draft immediately, and its public URL starts returning a 404.',
            variant: 'destructive',
            confirmLabel: 'Unpublish',
        });

        if (ok) {
            router.delete(r('posts.unpublish', post.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<Post> = {
        title: (row) => (
            <div className="min-w-0">
                {mayUpdate ? (
                    <Link
                        href={r('posts.edit', row.id)}
                        className="block truncate rounded-sm text-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {row.title}
                    </Link>
                ) : (
                    <span className="block truncate text-sm font-medium">{row.title}</span>
                )}
                <span className="block truncate text-xs text-muted-foreground">/{row.slug}</span>
            </div>
        ),
        status: (row) => (
            <span className="inline-flex items-center gap-1.5">
                <EnumBadgeCell value={row.status} map={STATUS_BADGES} />
                {row.is_featured && <Badge variant="outline">Featured</Badge>}
            </span>
        ),
        category: (row) => <TextCell value={row.category} muted />,
        author: (row) => <TextCell value={row.author} />,
        published_at: (row) => <DateCell value={row.published_at} />,
        reading_time: (row) => <span className="tabular-nums">{row.reading_time > 0 ? `${row.reading_time} min` : '—'}</span>,
        view_count: (row) => <span className="tabular-nums">{row.view_count}</span>,
        comments_count: (row) => <span className="tabular-nums">{row.comments_count ?? 0}</span>,
        updated_at: (row) => <DateCell value={row.updated_at} />,
    };

    function rowActions(row: Post): RowAction[] {
        const actions: RowAction[] = [];

        if (row.is_published && row.url) {
            actions.push({
                id: 'view',
                label: 'View live',
                icon: <ExternalLink className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => {
                    window.open(row.url ?? '', '_blank', 'noopener');
                },
            });
        }

        if (mayUpdate) {
            actions.push({
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(r('posts.edit', row.id)),
            });
        }

        if (can.create) {
            actions.push({
                id: 'duplicate',
                label: 'Duplicate',
                icon: <Copy className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.post(r('posts.duplicate', row.id), {}, { preserveScroll: true }),
            });
        }

        if (can.publish) {
            actions.push(
                row.is_published
                    ? {
                          id: 'unpublish',
                          label: 'Unpublish',
                          separatorBefore: true,
                          icon: <EyeOff className="size-4 opacity-70" aria-hidden="true" />,
                          onSelect: () => void unpublish(row),
                      }
                    : {
                          id: 'publish',
                          label: 'Publish now',
                          separatorBefore: true,
                          icon: <Send className="size-4 opacity-70" aria-hidden="true" />,
                          onSelect: () => router.post(r('posts.publish', row.id), {}, { preserveScroll: true }),
                      },
            );
        }

        if (can.delete) {
            actions.push({
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            });
        }

        return actions;
    }

    return (
        <Layout title="Posts" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Posts"
                    description="Everything this workspace has written, drafted or scheduled."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={r('posts.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New post
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                <DataTable<Post>
                    payload={table}
                    propKey="table"
                    name="posts"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search posts…"
                    caption="Blog posts in this workspace"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No posts match these filters"
                                description="Clear the status or category filter to see everything again."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={BookOpen}
                                title="No posts yet"
                                description="Write the first one — it will stay a draft until you publish it."
                                className="border-0"
                                action={
                                    can.create ? (
                                        <Button asChild size="sm">
                                            <Link href={r('posts.create')}>
                                                <Plus className="size-4" aria-hidden="true" />
                                                New post
                                            </Link>
                                        </Button>
                                    ) : null
                                }
                            />
                        )
                    }
                    toolbar={
                        may('blog.comments.moderate') ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={r('comments.index')}>
                                    <Eye className="size-4" aria-hidden="true" />
                                    Comments
                                </Link>
                            </Button>
                        ) : null
                    }
                />
            </div>
        </Layout>
    );
}
