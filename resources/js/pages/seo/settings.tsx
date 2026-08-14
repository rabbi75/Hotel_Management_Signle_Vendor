import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import type { SeoSettingsValues } from '@/types/seo';
import { useForm } from '@inertiajs/react';
import { CircleAlert, TriangleAlert } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useSeoPanel } from './use-panel';

interface SeoSettingsProps {
    settings: SeoSettingsValues;
    can: { update: boolean };
}

interface FormValues {
    default_title: string;
    default_description: string;
    title_suffix: string;
    title_max: number;
    description_max: number;
    default_og_image: string;
    twitter_handle: string;
    robots_indexable: boolean;
    [key: string]: string | number | boolean;
}

export default function SeoSettings({ settings, can }: SeoSettingsProps) {
    const { Layout, home, r } = useSeoPanel();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl(home) ?? undefined },
        { label: 'SEO', href: r('index') },
        { label: 'Defaults' },
    ];

    const form = useForm<FormValues>({
        default_title: settings.default_title ?? '',
        default_description: settings.default_description ?? '',
        title_suffix: settings.title_suffix ?? '',
        title_max: settings.title_max,
        description_max: settings.description_max,
        default_og_image: settings.default_og_image ?? '',
        twitter_handle: settings.twitter_handle ?? '',
        robots_indexable: settings.robots_indexable,
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.patch(r('settings.update'), { preserveScroll: true });
    }

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <Layout title="SEO defaults" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="SEO defaults"
                    description="What every page in this workspace says about itself unless it overrides it."
                />

                <form onSubmit={submit} noValidate className="max-w-3xl space-y-6">
                    {hasErrors && (
                        <Alert variant="destructive">
                            <CircleAlert className="size-4" aria-hidden="true" />
                            <AlertTitle>These settings could not be saved</AlertTitle>
                            <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                        </Alert>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Search engine visibility</CardTitle>
                            <CardDescription>The switch that governs robots.txt and every page&apos;s robots tag.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-start justify-between gap-4 rounded-md border border-border p-4">
                                <div className="min-w-0 space-y-1">
                                    <Label htmlFor="robots_indexable">Allow search engines to index this site</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Off by default so a staging deploy is never crawled by accident. While it is off, robots.txt answers{' '}
                                        <code className="rounded bg-muted px-1 py-0.5 text-xs">Disallow: /</code> and every page is sent as{' '}
                                        <code className="rounded bg-muted px-1 py-0.5 text-xs">noindex</code>.
                                    </p>
                                </div>
                                <Switch
                                    id="robots_indexable"
                                    checked={data.robots_indexable}
                                    onCheckedChange={(next) => setData('robots_indexable', next)}
                                    disabled={!can.update}
                                />
                            </div>

                            {!data.robots_indexable && (
                                <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden="true" />
                                    Nothing you publish will reach search results until this is on.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Fallback metadata</CardTitle>
                            <CardDescription>Used when a page has not been given its own.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <Field name="default_title" label="Default title" error={errors.default_title}>
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.default_title}
                                        onChange={(event) => setData('default_title', event.target.value)}
                                        disabled={!can.update}
                                    />
                                )}
                            </Field>

                            <Field
                                name="title_suffix"
                                label="Title suffix"
                                hint="Appended to every title, e.g. “How we ship — Acme”. Leave blank for none."
                                error={errors.title_suffix}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.title_suffix}
                                        onChange={(event) => setData('title_suffix', event.target.value)}
                                        disabled={!can.update}
                                    />
                                )}
                            </Field>

                            <Field name="default_description" label="Default description" error={errors.default_description}>
                                {(props) => (
                                    <Textarea
                                        {...props}
                                        rows={3}
                                        value={data.default_description}
                                        onChange={(event) => setData('default_description', event.target.value)}
                                        disabled={!can.update}
                                    />
                                )}
                            </Field>

                            <Field
                                name="default_og_image"
                                label="Default share image URL"
                                hint="Shown when a page has no image of its own. 1200×630."
                                error={errors.default_og_image}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.default_og_image}
                                        onChange={(event) => setData('default_og_image', event.target.value)}
                                        disabled={!can.update}
                                    />
                                )}
                            </Field>

                            <Field
                                name="twitter_handle"
                                label="Twitter/X handle"
                                hint="Credited on shared cards, e.g. @acme."
                                error={errors.twitter_handle}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.twitter_handle}
                                        placeholder="@acme"
                                        onChange={(event) => setData('twitter_handle', event.target.value)}
                                        disabled={!can.update}
                                    />
                                )}
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Limits</CardTitle>
                            <CardDescription>
                                Where the editor&apos;s character counters turn amber and red. Search engines truncate at roughly these
                                lengths.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <Field name="title_max" label="Title length" error={errors.title_max} required>
                                {(props) => (
                                    <Input
                                        {...props}
                                        type="number"
                                        min={20}
                                        max={120}
                                        value={data.title_max}
                                        onChange={(event) => setData('title_max', Number(event.target.value))}
                                        disabled={!can.update}
                                    />
                                )}
                            </Field>

                            <Field name="description_max" label="Description length" error={errors.description_max} required>
                                {(props) => (
                                    <Input
                                        {...props}
                                        type="number"
                                        min={50}
                                        max={320}
                                        value={data.description_max}
                                        onChange={(event) => setData('description_max', Number(event.target.value))}
                                        disabled={!can.update}
                                    />
                                )}
                            </Field>
                        </CardContent>
                    </Card>

                    {can.update && (
                        <FormActions
                            dirty={isDirty}
                            submitting={processing}
                            saved={recentlySuccessful}
                            submitLabel="Save defaults"
                            onCancel={() => form.reset()}
                        />
                    )}
                </form>
            </div>
        </Layout>
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
    hint,
    error,
    required = false,
    children,
}: {
    name: string;
    label: string;
    hint?: string;
    error?: string | undefined;
    required?: boolean;
    children: (props: ControlProps) => ReactNode;
}) {
    const id = `seo-${name}`;
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
                <p id={hintId} className="text-xs text-muted-foreground">
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
