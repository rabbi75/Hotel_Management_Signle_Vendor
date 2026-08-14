import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BlogComment, Post } from '@/types/blog';
import { Head, Link, useForm } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { ArrowLeft, CircleAlert } from 'lucide-react';
import type { FormEvent } from 'react';

interface BlogShowProps {
    post: Post;
    comments: BlogComment[];
    comments_enabled: boolean;
    related: Post[];
}

export default function BlogShow({ post, comments, comments_enabled, related }: BlogShowProps) {
    return (
        <div className="min-h-svh bg-background text-foreground">
            <Head title={post.title} />

            <article className="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
                <Link
                    href={route('blog.public.index')}
                    className="mb-8 inline-flex items-center gap-1.5 rounded-sm text-sm text-muted-foreground underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    All posts
                </Link>

                <header className="space-y-4">
                    {post.category && <Badge variant="outline">{post.category}</Badge>}

                    <h1 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{post.title}</h1>

                    <p className="text-sm text-muted-foreground">
                        {post.published_at && (
                            <time dateTime={post.published_at}>{format(parseISO(post.published_at), 'PPP')}</time>
                        )}
                        {post.author && ` · ${post.author}`}
                        {post.reading_time > 0 && ` · ${post.reading_time} min read`}
                    </p>

                    {post.featured_image && (
                        <img
                            src={post.featured_image}
                            alt=""
                            className="aspect-video w-full rounded-lg bg-muted object-cover"
                        />
                    )}
                </header>

                {/*
                    Safe: body_html is produced server-side — markdown converted with
                    html_input=strip, or rich text run through HtmlSanitizer's allow
                    list. The raw `body` the author typed is never rendered.
                */}
                <div
                    className="prose-content mt-8"
                    dangerouslySetInnerHTML={{ __html: post.body_html ?? '' }}
                />

                {post.tags.length > 0 && (
                    <ul className="mt-8 flex flex-wrap gap-2 border-t border-border pt-6">
                        {post.tags.map((tag) => (
                            <li key={tag}>
                                <Badge variant="secondary">#{tag}</Badge>
                            </li>
                        ))}
                    </ul>
                )}

                {related.length > 0 && (
                    <section aria-labelledby="related-heading" className="mt-12 border-t border-border pt-8">
                        <h2 id="related-heading" className="mb-4 text-lg font-medium">
                            More in {post.category}
                        </h2>
                        <ul className="space-y-3">
                            {related.map((item) => (
                                <li key={item.id}>
                                    <Link
                                        href={route('blog.public.show', item.slug)}
                                        className="rounded-sm text-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        {item.title}
                                    </Link>
                                    {item.excerpt && <p className="line-clamp-2 text-sm text-muted-foreground">{item.excerpt}</p>}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {comments_enabled && (
                    <section aria-labelledby="comments-heading" className="mt-12 border-t border-border pt-8">
                        <h2 id="comments-heading" className="mb-6 text-lg font-medium">
                            {comments.length === 0 ? 'Comments' : `${comments.length} comments`}
                        </h2>

                        {comments.length > 0 && (
                            <ul className="mb-8 space-y-6">
                                {comments.map((comment) => (
                                    <CommentItem key={comment.id} comment={comment} depth={0} />
                                ))}
                            </ul>
                        )}

                        <CommentForm slug={post.slug} />
                    </section>
                )}
            </article>
        </div>
    );
}

function CommentItem({ comment, depth }: { comment: BlogComment; depth: number }) {
    return (
        <li style={{ paddingInlineStart: `${Math.min(depth, 3) * 1.5}rem` }}>
            <div className="rounded-lg border border-border bg-card p-4">
                <p className="text-sm font-medium">{comment.author}</p>
                {comment.created_at && (
                    <p className="text-xs text-muted-foreground">
                        <time dateTime={comment.created_at}>{format(parseISO(comment.created_at), 'PP')}</time>
                    </p>
                )}
                {/* Plain text on purpose — comments have no markup path. */}
                <p className="mt-2 text-sm whitespace-pre-line">{comment.body}</p>
            </div>

            {comment.replies.length > 0 && (
                <ul className="mt-3 space-y-3">
                    {comment.replies.map((reply) => (
                        <CommentItem key={reply.id} comment={reply} depth={depth + 1} />
                    ))}
                </ul>
            )}
        </li>
    );
}

interface CommentFormValues {
    body: string;
    guest_name: string;
    guest_email: string;
    website: string;
    [key: string]: string;
}

function CommentForm({ slug }: { slug: string }) {
    const form = useForm<CommentFormValues>({
        body: '',
        guest_name: '',
        guest_email: '',
        website: '',
    });

    const { data, setData, errors, processing, recentlySuccessful } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.post(route('blog.public.comment', slug), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-4 rounded-lg border border-border p-5">
            <h3 className="text-sm font-medium">Leave a comment</h3>

            {recentlySuccessful && (
                <p className="text-sm text-success" role="status">
                    Thanks — your comment has been submitted.
                </p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="guest_name">Name</Label>
                    <Input
                        id="guest_name"
                        value={data.guest_name}
                        onChange={(event) => setData('guest_name', event.target.value)}
                        aria-invalid={errors.guest_name ? true : undefined}
                        required
                    />
                    {errors.guest_name && <FieldError message={errors.guest_name} />}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="guest_email">Email</Label>
                    <Input
                        id="guest_email"
                        type="email"
                        value={data.guest_email}
                        onChange={(event) => setData('guest_email', event.target.value)}
                        aria-invalid={errors.guest_email ? true : undefined}
                        required
                    />
                    {errors.guest_email && <FieldError message={errors.guest_email} />}
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="body">Comment</Label>
                <Textarea
                    id="body"
                    rows={4}
                    value={data.body}
                    onChange={(event) => setData('body', event.target.value)}
                    aria-invalid={errors.body ? true : undefined}
                    required
                />
                {errors.body && <FieldError message={errors.body} />}
            </div>

            {/* Honeypot: never shown to a human, always filled by a bot. */}
            <div aria-hidden="true" className="absolute -left-[9999px] h-0 w-0 overflow-hidden">
                <label htmlFor="website">Website</label>
                <input
                    id="website"
                    type="text"
                    tabIndex={-1}
                    autoComplete="off"
                    value={data.website}
                    onChange={(event) => setData('website', event.target.value)}
                />
            </div>

            <p className="text-xs text-muted-foreground">Comments are reviewed before they appear.</p>

            <Button type="submit" disabled={processing}>
                {processing ? 'Submitting…' : 'Post comment'}
            </Button>
        </form>
    );
}

function FieldError({ message }: { message: string }) {
    return (
        <p className="flex items-start gap-1.5 text-sm text-destructive">
            <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            {message}
        </p>
    );
}
