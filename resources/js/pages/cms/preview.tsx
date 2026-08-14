import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import type { SharedProps } from '@/types';
import type { PublicPageProps } from '@/types/cms';
import { Head, usePage } from '@inertiajs/react';
import { Eye, FileQuestion } from 'lucide-react';
import { RenderedBlocks } from './block-renderer';
import { PublicShell } from './public-shell';

/**
 * Draft preview.
 *
 * Rendered in the same shell as the live site so what the author approves is
 * what ships — but deliberately not indexable, and visibly badged: an
 * unpublished page that looks exactly like the live site is how people publish
 * the wrong thing.
 */
export default function CmsPreview() {
    const { page, menus, expiresIn } = usePage<SharedProps & PublicPageProps>().props;
    const minutes = expiresIn ? Math.round(expiresIn / 60) : null;

    return (
        <PublicShell
            header={menus?.header ?? []}
            footer={menus?.footer ?? []}
            banner={
                <div className="flex flex-wrap items-center gap-3 border-b border-border bg-warning px-4 py-2 text-warning-foreground">
                    <Eye className="size-4 shrink-0" aria-hidden="true" />
                    <p className="text-sm font-medium">Preview of an unpublished page — nobody else can see this.</p>
                    <Badge variant="outline" className="ml-auto border-warning-foreground/30 text-warning-foreground">
                        {page.status}
                    </Badge>
                    {minutes !== null && <span className="text-xs">Link valid for about {minutes} minutes</span>}
                </div>
            }
        >
            <Head title={`Preview — ${page.title}`}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <h1 className="sr-only">{page.title}</h1>

            {page.blocks.length === 0 ? (
                <div className="px-6 py-24">
                    <EmptyState icon={FileQuestion} title="Nothing to preview yet" description="Add a block to this page first." />
                </div>
            ) : (
                <RenderedBlocks blocks={page.blocks} showHiddenMarkers />
            )}
        </PublicShell>
    );
}
