import { EmptyState } from '@/components/ui/empty-state';
import type { SharedProps } from '@/types';
import type { PublicPageProps } from '@/types/cms';
import { Head, usePage } from '@inertiajs/react';
import { FileQuestion } from 'lucide-react';
import { RenderedBlocks } from './block-renderer';
import { PublicShell } from './public-shell';

export default function PublicPage() {
    const { page, menus } = usePage<SharedProps & PublicPageProps>().props;

    return (
        <PublicShell header={menus?.header ?? []} footer={menus?.footer ?? []}>
            <Head title={page.seo.title || page.title}>
                {page.seo.description && <meta name="description" content={page.seo.description} />}
                {page.seo.keywords && <meta name="keywords" content={page.seo.keywords} />}
                {page.seo.canonical && <link rel="canonical" href={page.seo.canonical} />}
                {page.seo.og_image && <meta property="og:image" content={page.seo.og_image} />}
                <meta property="og:title" content={page.seo.title || page.title} />
                {page.seo.noindex && <meta name="robots" content="noindex, nofollow" />}
            </Head>

            <h1 className="sr-only">{page.title}</h1>

            {page.blocks.length === 0 ? (
                <div className="px-6 py-24">
                    <EmptyState icon={FileQuestion} title="This page has no content yet" description="Its blocks have not been published." />
                </div>
            ) : (
                <RenderedBlocks blocks={page.blocks} />
            )}
        </PublicShell>
    );
}
