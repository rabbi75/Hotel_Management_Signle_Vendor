import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import type { BreadcrumbItem } from '@/types';
import type { BlogOption } from '@/types/blog';
import type { SeoContext } from '@/types/seo';
import { useBlogPanel } from '../use-panel';
import { PostForm } from './post-form';

interface PostCreateProps {
    categories: BlogOption[];
    tags: BlogOption[];
    seo: SeoContext;
}

export default function PostCreate({ categories, tags, seo }: PostCreateProps) {
    const { Layout, home, r } = useBlogPanel();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl(home) ?? undefined },
        { label: 'Posts', href: r('posts.index') },
        { label: 'New post' },
    ];

    return (
        <Layout title="New post" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="New post" description="It stays a draft — and returns a 404 to readers — until you publish it." />

                <PostForm categories={categories} tags={tags} seo={seo} />
            </div>
        </Layout>
    );
}
