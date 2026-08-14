import { FormActions } from '@/components/forms/form-actions';
import { useAutosave } from '@/components/forms/use-autosave';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { BlogOption, BodyFormat, Post, PostStatus } from '@/types/blog';
import type { SeoContext, SeoMetaPayload, SeoPanelValue, SeoReport } from '@/types/seo';
import type { RequestPayload } from '@inertiajs/core';
import { router, useForm } from '@inertiajs/react';
import { CircleAlert, Clock, Eye, TriangleAlert } from 'lucide-react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { EMPTY_SEO, SeoPanel } from '../../seo/seo-panel';
import { useBlogPanel } from '../use-panel';
import { RichTextEditor } from './rich-text-editor';

/** Radix Select cannot hold an empty string value, so "none" needs a sentinel. */
const NONE = '__none__';

export interface PostFormProps {
    /** Omitted when creating. */
    post?: Post;
    categories: BlogOption[];
    tags: BlogOption[];
    seo: SeoContext;
    report?: SeoReport | null;
    can?: { publish: boolean };
}

interface PostFormValues {
    title: string;
    slug: string;
    excerpt: string;
    body: string;
    body_format: BodyFormat;
    status: PostStatus;
    published_at: string;
    category_id: string;
    tag_ids: number[];
    featured_image: string;
    is_featured: boolean;
    allow_comments: boolean;
    seo: SeoPanelValue;
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

/**
 * Drop `structured_data` from the server payload.
 *
 * It is generated per content type rather than edited here, and carrying an
 * arbitrary object through the form would make the whole form unserialisable.
 */
function toPanelValue(meta: SeoMetaPayload | null): SeoPanelValue {
    if (meta === null) {
        return EMPTY_SEO;
    }

    const { structured_data: _ignored, ...rest } = meta;

    return rest;
}

/** `datetime-local` wants `YYYY-MM-DDTHH:mm` in local time, not an ISO string. */
function toLocalInput(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function PostForm({ post, categories, tags, seo, report, can }: PostFormProps) {
    const { r } = useBlogPanel();
    const editing = post !== undefined;

    const form = useForm<PostFormValues>({
        title: post?.title ?? '',
        slug: post?.slug ?? '',
        excerpt: post?.excerpt ?? '',
        body: post?.body ?? '',
        body_format: post?.body_format ?? 'markdown',
        status: post?.status ?? 'draft',
        published_at: toLocalInput(post?.published_at ?? null),
        category_id: post?.category_id === null || post?.category_id === undefined ? '' : String(post.category_id),
        tag_ids: post?.tag_ids ?? [],
        featured_image: post?.featured_image ?? '',
        is_featured: post?.is_featured ?? false,
        allow_comments: post?.allow_comments ?? true,
        seo: toPanelValue(post?.seo ?? null),
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    const [slugTouched, setSlugTouched] = useState(editing && Boolean(post?.slug));
    const [autosaveOn, setAutosaveOn] = useState(editing);

    const wasPublished = post?.is_published ?? false;
    const slugChanged = editing && data.slug !== post?.slug;

    // Autosave writes the whole form, so PostData sees every key and the
    // absent/null distinction stays meaningful. Only ever enabled while editing:
    // a create screen has nothing to PATCH.
    const autosave = useAutosave<PostFormValues>({
        values: data,
        enabled: autosaveOn && editing && !processing,
        delay: 2000,
        save: (values) =>
            new Promise<void>((resolve, reject) => {
                router.patch(r('posts.update', post?.id ?? 0), payload(values), {
                    preserveScroll: true,
                    preserveState: true,
                    only: [],
                    onSuccess: () => resolve(),
                    onError: () => reject(new Error('Some fields could not be saved — open the form to see why.')),
                });
            }),
    });

    function payload(values: PostFormValues): RequestPayload {
        return {
            ...values,
            slug: values.slug === '' ? null : values.slug,
            excerpt: values.excerpt === '' ? null : values.excerpt,
            category_id: values.category_id === '' ? null : values.category_id,
            featured_image: values.featured_image === '' ? null : values.featured_image,
            published_at: values.published_at === '' ? null : values.published_at,
        };
    }

    function onTitleChange(value: string): void {
        setData((current) => ({
            ...current,
            title: value,
            // The slug follows the title only until somebody edits it, and never
            // once the post is live: a changed URL breaks every inbound link.
            slug: slugTouched || wasPublished ? current.slug : slugify(value),
        }));
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.transform(payload);

        if (editing && post) {
            form.patch(r('posts.update', post.id), { preserveScroll: true });

            return;
        }

        form.post(r('posts.store'));
    }

    const selectedTags = useMemo(() => new Set(data.tag_ids), [data.tag_ids]);

    function toggleTag(id: number, checked: boolean): void {
        setData('tag_ids', checked ? [...data.tag_ids, id] : data.tag_ids.filter((current) => current !== id));
    }

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {hasErrors && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This post could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="min-w-0 space-y-6">
                    <Card>
                        <CardContent className="space-y-5 py-6">
                            <Field name="title" label="Title" error={errors.title} required>
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.title}
                                        onChange={(event) => onTitleChange(event.target.value)}
                                        className="text-lg font-medium"
                                    />
                                )}
                            </Field>

                            <Field
                                name="slug"
                                label="URL slug"
                                error={errors.slug}
                                hint={`${seo.base_url}/blog/${data.slug || 'your-post'}`}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.slug}
                                        onChange={(event) => {
                                            setSlugTouched(true);
                                            setData('slug', slugify(event.target.value));
                                        }}
                                    />
                                )}
                            </Field>

                            {wasPublished && slugChanged && (
                                <p className="flex items-start gap-2 rounded-md border border-warning/40 bg-warning/10 p-3 text-sm">
                                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden="true" />
                                    <span>
                                        This post is live at <code className="rounded bg-muted px-1 py-0.5 text-xs">/{post?.slug}</code>.
                                        Saving a new slug changes its public URL — every existing link, share and search result pointing at
                                        the old one will 404. Set up a redirect first if anything links here.
                                    </span>
                                </p>
                            )}

                            <Field
                                name="excerpt"
                                label="Excerpt"
                                error={errors.excerpt}
                                hint="Shown in listings and used as the default meta description. Leave blank to derive it from the body."
                            >
                                {(props) => (
                                    <Textarea
                                        {...props}
                                        rows={2}
                                        value={data.excerpt}
                                        onChange={(event) => setData('excerpt', event.target.value)}
                                    />
                                )}
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between gap-3 space-y-0">
                            <div className="min-w-0">
                                <CardTitle>Body</CardTitle>
                                <CardDescription>
                                    {data.body_format === 'markdown'
                                        ? 'Markdown. Raw HTML is stripped when it is rendered.'
                                        : 'Rich text. The markup is re-sanitised on the server when you save.'}
                                </CardDescription>
                            </div>

                            <Select value={data.body_format} onValueChange={(value) => setData('body_format', value as BodyFormat)}>
                                <SelectTrigger className="w-40" aria-label="Body format">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="markdown">Markdown</SelectItem>
                                    <SelectItem value="html">Rich text</SelectItem>
                                </SelectContent>
                            </Select>
                        </CardHeader>

                        <CardContent>
                            {data.body_format === 'html' ? (
                                <RichTextEditor
                                    id="post-body"
                                    value={data.body}
                                    onChange={(html) => setData('body', html)}
                                    placeholder="Start writing…"
                                    aria-describedby={errors.body ? 'post-body-error' : undefined}
                                />
                            ) : (
                                <MarkdownEditor
                                    value={data.body}
                                    onChange={(next) => setData('body', next)}
                                    renderedHtml={post?.body_html ?? null}
                                />
                            )}

                            {errors.body && (
                                <p id="post-body-error" className="mt-2 flex items-start gap-1.5 text-sm text-destructive">
                                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    {errors.body}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <SeoPanel
                        value={data.seo}
                        onChange={(next) => setData('seo', next)}
                        context={seo}
                        fallback={{
                            title: data.title,
                            description: data.excerpt || null,
                            slug: data.slug,
                            image: data.featured_image || null,
                        }}
                        report={report ?? null}
                    />
                </div>

                {/* ------------------------------------------------------ Sidebar */}
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Publishing</CardTitle>
                            <CardDescription>
                                {data.status === 'scheduled'
                                    ? 'The post goes live automatically at the time below.'
                                    : 'Drafts are invisible to readers — their URL returns a 404.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <Field name="status" label="Status" error={errors.status}>
                                {(props) => (
                                    <Select
                                        value={data.status}
                                        onValueChange={(value) => setData('status', value as PostStatus)}
                                        disabled={can !== undefined && !can.publish}
                                    >
                                        <SelectTrigger {...props}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="draft">Draft</SelectItem>
                                            <SelectItem value="scheduled">Scheduled</SelectItem>
                                            <SelectItem value="published">Published</SelectItem>
                                            <SelectItem value="archived">Archived</SelectItem>
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>

                            {(data.status === 'scheduled' || data.status === 'published') && (
                                <Field
                                    name="published_at"
                                    label={data.status === 'scheduled' ? 'Goes live at' : 'Published at'}
                                    error={errors.published_at}
                                    hint="Your local time."
                                    required={data.status === 'scheduled'}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            type="datetime-local"
                                            value={data.published_at}
                                            onChange={(event) => setData('published_at', event.target.value)}
                                        />
                                    )}
                                </Field>
                            )}

                            {editing && post?.is_published && post.url && (
                                <Button variant="outline" size="sm" asChild className="w-full">
                                    <a href={post.url} target="_blank" rel="noreferrer">
                                        <Eye className="size-4" aria-hidden="true" />
                                        View live post
                                    </a>
                                </Button>
                            )}

                            {editing && (
                                <div className="flex items-start justify-between gap-3 border-t border-border pt-4">
                                    <div className="min-w-0 space-y-0.5">
                                        <Label htmlFor="autosave">Autosave</Label>
                                        <p className="text-xs text-muted-foreground" aria-live="polite">
                                            <Clock className="mr-1 inline size-3" aria-hidden="true" />
                                            {autosaveOn ? autosave.message : 'Off — save manually'}
                                        </p>
                                    </div>
                                    <Switch id="autosave" checked={autosaveOn} onCheckedChange={setAutosaveOn} />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Organisation</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <Field name="category_id" label="Category" error={errors.category_id}>
                                {(props) => (
                                    <Select
                                        value={data.category_id === '' ? NONE : data.category_id}
                                        onValueChange={(value) => setData('category_id', value === NONE ? '' : value)}
                                    >
                                        <SelectTrigger {...props}>
                                            <SelectValue placeholder="Uncategorised" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>Uncategorised</SelectItem>
                                            {categories.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>

                            <fieldset className="space-y-2">
                                <legend className="text-sm font-medium">Tags</legend>

                                {tags.length === 0 ? (
                                    <p className="text-xs text-muted-foreground">No tags yet. Create some under Content → Tags.</p>
                                ) : (
                                    <div className="max-h-56 space-y-2 overflow-y-auto rounded-md border border-border p-3">
                                        {tags.map((option) => {
                                            const id = Number(option.value);

                                            return (
                                                <label key={option.value} className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={selectedTags.has(id)}
                                                        onCheckedChange={(checked) => toggleTag(id, checked === true)}
                                                    />
                                                    <span className="min-w-0 truncate">{option.label}</span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}

                                {errors.tag_ids && (
                                    <p className="flex items-start gap-1.5 text-sm text-destructive">
                                        <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                        {errors.tag_ids}
                                    </p>
                                )}
                            </fieldset>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Featured image</CardTitle>
                            <CardDescription>Used in listings and as the default share image.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field name="featured_image" label="Image URL" error={errors.featured_image}>
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.featured_image}
                                        placeholder="https://…"
                                        onChange={(event) => setData('featured_image', event.target.value)}
                                    />
                                )}
                            </Field>

                            {data.featured_image !== '' && (
                                <div className="overflow-hidden rounded-md border border-border">
                                    <img
                                        src={data.featured_image}
                                        alt=""
                                        className="aspect-video w-full bg-muted object-cover"
                                        loading="lazy"
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Options</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <Label htmlFor="is_featured">Feature this post</Label>
                                <Switch
                                    id="is_featured"
                                    checked={data.is_featured}
                                    onCheckedChange={(next) => setData('is_featured', next)}
                                />
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <Label htmlFor="allow_comments">Allow comments</Label>
                                <Switch
                                    id="allow_comments"
                                    checked={data.allow_comments}
                                    onCheckedChange={(next) => setData('allow_comments', next)}
                                />
                            </div>

                            {editing && (
                                <dl className="grid gap-1.5 border-t border-border pt-4 text-xs text-muted-foreground">
                                    <div className="flex justify-between gap-3">
                                        <dt>Reading time</dt>
                                        <dd className="tabular-nums">{post?.reading_time ?? 0} min</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt>Views</dt>
                                        <dd className="tabular-nums">{post?.view_count ?? 0}</dd>
                                    </div>
                                </dl>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save post' : 'Create post'}
                onCancel={() => form.reset()}
            >
                {editing && autosaveOn && autosave.status === 'error' && <Badge variant="destructive">{autosave.message}</Badge>}
            </FormActions>
        </form>
    );
}

/* -------------------------------------------------------------------------- */

/**
 * Markdown with a preview of the *last saved* render.
 *
 * The preview is deliberately server-produced rather than converted in the
 * browser: rendering unsanitised markdown client-side would mean injecting
 * author HTML straight into the editing session.
 */
function MarkdownEditor({
    value,
    onChange,
    renderedHtml,
}: {
    value: string;
    onChange: (value: string) => void;
    renderedHtml: string | null;
}) {
    return (
        <Tabs defaultValue="write" className="gap-3">
            <TabsList>
                <TabsTrigger value="write">Write</TabsTrigger>
                <TabsTrigger value="preview">Preview</TabsTrigger>
            </TabsList>

            <TabsContent value="write">
                <Textarea
                    id="post-body"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    rows={20}
                    spellCheck
                    placeholder={'# A heading\n\nWrite in markdown. **Bold**, _italic_, [links](https://example.com) and lists all work.'}
                    className="font-mono text-sm"
                />
            </TabsContent>

            <TabsContent value="preview">
                <div className={cn('min-h-[24rem] rounded-md border border-border p-4')}>
                    {renderedHtml ? (
                        <>
                            <p className="mb-3 text-xs text-muted-foreground">Rendered as of the last save.</p>
                            {/* Safe: body_html is produced by the server's markdown
                                converter with html_input=strip, never by the client. */}
                            <div className="prose-content" dangerouslySetInnerHTML={{ __html: renderedHtml }} />
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">Save the post to see how it renders.</p>
                    )}
                </div>
            </TabsContent>
        </Tabs>
    );
}

interface ControlProps {
    id: string;
    'aria-invalid': true | undefined;
    'aria-describedby': string | undefined;
    required: boolean | undefined;
}

function Field({
    name,
    label,
    error,
    hint,
    required = false,
    children,
}: {
    name: string;
    label: string;
    error?: string | undefined;
    hint?: string;
    required?: boolean;
    children: (props: ControlProps) => ReactNode;
}) {
    const id = `post-${name}`;
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const describedBy = [error ? errorId : null, hint ? hintId : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                )}
            </Label>

            {children({
                id,
                'aria-invalid': error ? true : undefined,
                'aria-describedby': describedBy,
                required: required || undefined,
            })}

            {hint && !error && (
                <p id={hintId} className="truncate text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            {error && (
                <p id={errorId} className="flex items-start gap-1.5 text-sm text-destructive">
                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    {error}
                </p>
            )}
        </div>
    );
}
