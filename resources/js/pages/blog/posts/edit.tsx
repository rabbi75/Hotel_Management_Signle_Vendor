import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import type { BlogOption, Post } from '@/types/blog';
import type { SeoContext, SeoReport } from '@/types/seo';
import { router } from '@inertiajs/react';
import { Copy, Trash2 } from 'lucide-react';
import { useBlogPanel } from '../use-panel';
import { PostForm } from './post-form';

interface PostEditProps {
    post: Post;
    categories: BlogOption[];
    tags: BlogOption[];
    seo: SeoContext;
    report: SeoReport;
    can: { publish: boolean; delete: boolean; duplicate: boolean };
}

export default function PostEdit({ post, categories, tags, seo, report, can }: PostEditProps) {
    const { Layout, home, r } = useBlogPanel();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl(home) ?? undefined },
        { label: 'Posts', href: r('posts.index') },
        { label: post.title },
    ];

    async function remove(): Promise<void> {
        const ok = await confirm({
            title: `Delete “${post.title}”?`,
            description: post.is_published
                ? 'This post is live. Deleting it turns its URL into a 404 for anyone holding a link to it.'
                : 'The post moves to the trash and disappears from the list.',
            variant: 'destructive',
            confirmLabel: 'Delete post',
        });

        if (ok) {
            router.delete(r('posts.destroy', post.id));
        }
    }

    return (
        <Layout title={post.title} breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title={post.title || 'Untitled post'}
                    description={
                        <span className="inline-flex flex-wrap items-center gap-2">
                            <Badge variant={post.is_published ? 'success' : 'secondary'}>{post.status_label}</Badge>
                            <span>Last updated {post.updated_at ? new Date(post.updated_at).toLocaleString() : 'just now'}</span>
                        </span>
                    }
                    actions={
                        <>
                            {can.duplicate && (
                                <Button variant="outline" onClick={() => router.post(r('posts.duplicate', post.id))}>
                                    <Copy className="size-4" aria-hidden="true" />
                                    Duplicate
                                </Button>
                            )}
                            {can.delete && (
                                <Button variant="outline" onClick={() => void remove()}>
                                    <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                                    Delete
                                </Button>
                            )}
                        </>
                    }
                />

                <PostForm post={post} categories={categories} tags={tags} seo={seo} report={report} can={{ publish: can.publish }} />
            </div>
        </Layout>
    );
}
