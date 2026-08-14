import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import type { Post, PublicArchive, PublicPostPage, PublicTaxonomy } from '@/types/blog';
import { Head, Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { BookOpen, Rss } from 'lucide-react';

interface BlogIndexProps {
    posts: PublicPostPage;
    categories: PublicTaxonomy[];
    tags: PublicTaxonomy[];
    archive: PublicArchive | null;
}

/**
 * The reader-facing listing. Deliberately not wrapped in AppLayout: this page
 * is served to anonymous visitors and must not render the application shell,
 * the workspace switcher or anything else that assumes a session.
 */
export default function BlogIndex({ posts, categories, tags, archive }: BlogIndexProps) {
    const title = archive ? archive.name : 'Blog';

    return (
        <div className="min-h-svh bg-background text-foreground">
            <Head title={title} />

            <div className="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <header className="mb-10 space-y-3">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0 space-y-1">
                            {archive && (
                                <p className="text-sm text-muted-foreground">
                                    <Link href={route('blog.public.index')} className="underline-offset-4 hover:underline">
                                        Blog
                                    </Link>{' '}
                                    / {archive.type === 'category' ? 'Category' : 'Tag'}
                                </p>
                            )}
                            <h1 className="text-3xl font-semibold tracking-tight text-balance">{title}</h1>
                            {archive?.description && <p className="text-muted-foreground">{archive.description}</p>}
                        </div>

                        <Button variant="outline" size="sm" asChild>
                            <a href={route('blog.public.feed')}>
                                <Rss className="size-4" aria-hidden="true" />
                                RSS
                            </a>
                        </Button>
                    </div>

                    {(categories.length > 0 || tags.length > 0) && (
                        <nav aria-label="Browse" className="flex flex-wrap gap-2 pt-2">
                            {categories.map((category) => (
                                <Button key={`c-${category.id}`} variant="outline" size="sm" asChild>
                                    <Link href={route('blog.public.category', category.slug)}>{category.name}</Link>
                                </Button>
                            ))}
                            {tags.map((tag) => (
                                <Button key={`t-${tag.id}`} variant="ghost" size="sm" asChild>
                                    <Link href={route('blog.public.tag', tag.slug)}>#{tag.name}</Link>
                                </Button>
                            ))}
                        </nav>
                    )}
                </header>

                {posts.data.length === 0 ? (
                    <EmptyState icon={BookOpen} title="Nothing published yet" description="Check back soon." />
                ) : (
                    <ul className="grid gap-6 sm:grid-cols-2">
                        {posts.data.map((post) => (
                            <PostCard key={post.id} post={post} />
                        ))}
                    </ul>
                )}

                {posts.meta.last_page > 1 && (
                    <nav aria-label="Pagination" className="mt-10 flex items-center justify-between gap-4">
                        <Button
                            variant="outline"
                            disabled={posts.meta.current_page <= 1}
                            onClick={() => router.get(window.location.pathname, { page: posts.meta.current_page - 1 })}
                        >
                            Previous
                        </Button>

                        <p className="text-sm text-muted-foreground" aria-live="polite">
                            Page {posts.meta.current_page} of {posts.meta.last_page}
                        </p>

                        <Button
                            variant="outline"
                            disabled={posts.meta.current_page >= posts.meta.last_page}
                            onClick={() => router.get(window.location.pathname, { page: posts.meta.current_page + 1 })}
                        >
                            Next
                        </Button>
                    </nav>
                )}
            </div>
        </div>
    );
}

function PostCard({ post }: { post: Post }) {
    return (
        <li className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
            {post.featured_image && (
                <img src={post.featured_image} alt="" className="aspect-video w-full bg-muted object-cover" loading="lazy" />
            )}

            <div className="flex min-w-0 flex-1 flex-col gap-2 p-5">
                {post.category && <Badge variant="outline" className="w-fit">{post.category}</Badge>}

                <h2 className="text-lg font-medium text-balance">
                    <Link
                        href={route('blog.public.show', post.slug)}
                        className="rounded-sm underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {post.title}
                    </Link>
                </h2>

                {post.excerpt && <p className="line-clamp-3 flex-1 text-sm text-muted-foreground">{post.excerpt}</p>}

                <p className="text-xs text-muted-foreground">
                    {post.published_at && <time dateTime={post.published_at}>{format(parseISO(post.published_at), 'PP')}</time>}
                    {post.author && ` · ${post.author}`}
                    {post.reading_time > 0 && ` · ${post.reading_time} min read`}
                </p>
            </div>
        </li>
    );
}
