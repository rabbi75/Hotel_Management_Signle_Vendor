import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { SeoCheck, SeoCheckStatus, SeoContext, SeoPanelValue, SeoReport, TwitterCard } from '@/types/seo';
import { CircleAlert, CircleCheck, CircleX, Globe, ImageOff, TriangleAlert } from 'lucide-react';
import { useId, useMemo, type ReactNode } from 'react';

export const EMPTY_SEO: SeoPanelValue = {
    title: null,
    description: null,
    keywords: null,
    canonical_url: null,
    robots_index: true,
    robots_follow: true,
    og_title: null,
    og_description: null,
    og_image: null,
    twitter_card: 'summary_large_image',
    twitter_title: null,
    twitter_description: null,
    twitter_image: null,
};

const TWITTER_CARDS: { value: TwitterCard; label: string }[] = [
    { value: 'summary', label: 'Summary' },
    { value: 'summary_large_image', label: 'Summary with large image' },
    { value: 'app', label: 'App' },
    { value: 'player', label: 'Player' },
];

export interface SeoPanelProps {
    value: SeoPanelValue;
    onChange: (value: SeoPanelValue) => void;
    /** Limits and workspace defaults; drives the counters and the preview. */
    context: SeoContext;
    /** What the subject would say about itself if the panel were left blank. */
    fallback: {
        title: string;
        description: string | null;
        slug: string;
        image: string | null;
    };
    /** The scorer's findings. Omit while loading. */
    report?: SeoReport | null;
    disabled?: boolean;
    className?: string;
}

/**
 * The SEO editor embedded in the blog (and CMS) post editors.
 *
 * It shows the two things an author actually reacts to — what the result will
 * look like, and what is wrong with it — rather than a number. The character
 * counters change colour before the limit rather than at it, so a title is
 * fixed while it is being written instead of after it is truncated.
 */
export function SeoPanel({ value, onChange, context, fallback, report, disabled = false, className }: SeoPanelProps) {
    const ids = useId();

    function set<TKey extends keyof SeoPanelValue>(key: TKey, next: SeoPanelValue[TKey]): void {
        onChange({ ...value, [key]: next });
    }

    function setText(key: keyof SeoPanelValue, next: string): void {
        onChange({ ...value, [key]: next === '' ? null : next });
    }

    const effectiveTitle = value.title ?? fallback.title;
    const effectiveDescription = value.description ?? fallback.description ?? '';
    const previewTitle =
        context.title_suffix && !effectiveTitle.includes(context.title_suffix)
            ? `${effectiveTitle} — ${context.title_suffix}`
            : effectiveTitle;

    const previewUrl = useMemo(() => {
        if (value.canonical_url) {
            return value.canonical_url;
        }

        return `${context.base_url}/blog/${fallback.slug || 'your-post'}`;
    }, [value.canonical_url, context.base_url, fallback.slug]);

    const socialImage = value.og_image ?? fallback.image;

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    <Globe className="size-4" aria-hidden="true" />
                    Search &amp; social
                    {report && <ScoreBadge report={report} />}
                </CardTitle>
                <CardDescription>
                    Leave a field blank to inherit the workspace default. Nothing here changes the page itself — only how it is described to
                    search engines and social networks.
                </CardDescription>
            </CardHeader>

            <CardContent>
                {!context.indexable && (
                    <p className="mb-5 flex items-start gap-2 rounded-md border border-warning/40 bg-warning/10 p-3 text-sm text-foreground">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden="true" />
                        <span>
                            This workspace is marked <strong>noindex</strong>, so none of this is visible to search engines yet. Turn
                            indexing on under SEO&nbsp;→&nbsp;Defaults when the site is ready.
                        </span>
                    </p>
                )}

                <Tabs defaultValue="search" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="search">Search</TabsTrigger>
                        <TabsTrigger value="social">Social</TabsTrigger>
                        <TabsTrigger value="checks">
                            Checklist
                            {report && report.failed > 0 && (
                                <Badge variant="destructive" className="ml-1.5">
                                    {report.failed}
                                </Badge>
                            )}
                        </TabsTrigger>
                    </TabsList>

                    {/* ---------------------------------------------------- Search */}
                    <TabsContent value="search" className="space-y-5">
                        <GooglePreview title={previewTitle} url={previewUrl} description={effectiveDescription} />

                        <Field
                            id={`${ids}-title`}
                            label="SEO title"
                            hint="Shown as the clickable headline in a result."
                            counter={<Counter length={effectiveTitle.length} max={context.title_max} />}
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    value={value.title ?? ''}
                                    placeholder={fallback.title}
                                    onChange={(event) => setText('title', event.target.value)}
                                    disabled={disabled}
                                />
                            )}
                        </Field>

                        <Field
                            id={`${ids}-description`}
                            label="Meta description"
                            hint="The snippet beneath the title. Write it as a reason to click."
                            counter={<Counter length={effectiveDescription.length} max={context.description_max} />}
                        >
                            {(props) => (
                                <Textarea
                                    {...props}
                                    rows={3}
                                    value={value.description ?? ''}
                                    placeholder={fallback.description ?? ''}
                                    onChange={(event) => setText('description', event.target.value)}
                                    disabled={disabled}
                                />
                            )}
                        </Field>

                        <Field
                            id={`${ids}-keyword`}
                            label="Focus keyword"
                            hint="The phrase you want this page to rank for. Used by the checklist."
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    value={value.keywords ?? ''}
                                    onChange={(event) => setText('keywords', event.target.value)}
                                    disabled={disabled}
                                />
                            )}
                        </Field>

                        <Field
                            id={`${ids}-canonical`}
                            label="Canonical URL"
                            hint="Point duplicates of this page at one address. Leave blank to use the page's own URL."
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    type="url"
                                    inputMode="url"
                                    value={value.canonical_url ?? ''}
                                    placeholder={previewUrl}
                                    onChange={(event) => setText('canonical_url', event.target.value)}
                                    disabled={disabled}
                                />
                            )}
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Toggle
                                id={`${ids}-index`}
                                label="Allow indexing"
                                description="Off tells engines to keep this page out of results."
                                checked={value.robots_index}
                                onChange={(next) => set('robots_index', next)}
                                disabled={disabled}
                            />
                            <Toggle
                                id={`${ids}-follow`}
                                label="Follow links"
                                description="Off asks engines not to credit the pages this one links to."
                                checked={value.robots_follow}
                                onChange={(next) => set('robots_follow', next)}
                                disabled={disabled}
                            />
                        </div>
                    </TabsContent>

                    {/* ---------------------------------------------------- Social */}
                    <TabsContent value="social" className="space-y-5">
                        <SocialPreview
                            title={value.og_title ?? effectiveTitle}
                            description={value.og_description ?? effectiveDescription}
                            image={socialImage}
                            host={hostOf(previewUrl)}
                        />

                        <Field id={`${ids}-og-title`} label="Share title" hint="Defaults to the SEO title.">
                            {(props) => (
                                <Input
                                    {...props}
                                    value={value.og_title ?? ''}
                                    placeholder={effectiveTitle}
                                    onChange={(event) => setText('og_title', event.target.value)}
                                    disabled={disabled}
                                />
                            )}
                        </Field>

                        <Field id={`${ids}-og-description`} label="Share description" hint="Defaults to the meta description.">
                            {(props) => (
                                <Textarea
                                    {...props}
                                    rows={2}
                                    value={value.og_description ?? ''}
                                    placeholder={effectiveDescription}
                                    onChange={(event) => setText('og_description', event.target.value)}
                                    disabled={disabled}
                                />
                            )}
                        </Field>

                        <Field
                            id={`${ids}-og-image`}
                            label="Share image URL"
                            hint="1200×630 works everywhere. Defaults to the featured image."
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    value={value.og_image ?? ''}
                                    placeholder={fallback.image ?? 'https://…'}
                                    onChange={(event) => setText('og_image', event.target.value)}
                                    disabled={disabled}
                                />
                            )}
                        </Field>

                        <Field id={`${ids}-card`} label="Twitter card">
                            {(props) => (
                                <Select
                                    value={value.twitter_card}
                                    onValueChange={(next) => set('twitter_card', next as TwitterCard)}
                                    disabled={disabled}
                                >
                                    <SelectTrigger {...props}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {TWITTER_CARDS.map((card) => (
                                            <SelectItem key={card.value} value={card.value}>
                                                {card.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    </TabsContent>

                    {/* -------------------------------------------------- Checklist */}
                    <TabsContent value="checks">
                        {report ? <Checklist report={report} /> : <p className="text-sm text-muted-foreground">Save to run the checks.</p>}
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>
    );
}

/* -------------------------------------------------------------------------- */

function hostOf(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return '';
    }
}

interface ControlProps {
    id: string;
    'aria-describedby': string | undefined;
}

function Field({
    id,
    label,
    hint,
    counter,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    counter?: ReactNode;
    children: (props: ControlProps) => ReactNode;
}) {
    const hintId = hint ? `${id}-hint` : undefined;

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <Label htmlFor={id}>{label}</Label>
                {counter}
            </div>
            {children({ id, 'aria-describedby': hintId })}
            {hint && (
                <p id={hintId} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
        </div>
    );
}

/**
 * Amber at 90% of the limit, red past it — a counter that only turns red once
 * the text is already too long has told the author nothing they could not see.
 */
function Counter({ length, max }: { length: number; max: number }) {
    const over = length > max;
    const near = !over && length >= Math.floor(max * 0.9);

    return (
        <span
            className={cn(
                'text-xs tabular-nums',
                over ? 'font-medium text-destructive' : near ? 'font-medium text-warning' : 'text-muted-foreground',
            )}
            aria-live="polite"
        >
            {length}/{max}
            {over && <span className="sr-only"> — over the limit</span>}
        </span>
    );
}

function Toggle({
    id,
    label,
    description,
    checked,
    onChange,
    disabled,
}: {
    id: string;
    label: string;
    description: string;
    checked: boolean;
    onChange: (next: boolean) => void;
    disabled: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-3 rounded-md border border-border p-3">
            <div className="min-w-0 space-y-0.5">
                <Label htmlFor={id}>{label}</Label>
                <p className="text-xs text-muted-foreground">{description}</p>
            </div>
            <Switch id={id} checked={checked} onCheckedChange={onChange} disabled={disabled} />
        </div>
    );
}

function GooglePreview({ title, url, description }: { title: string; url: string; description: string }) {
    return (
        <div className="rounded-lg border border-border bg-muted/40 p-4">
            <p className="mb-2 text-xs font-medium text-muted-foreground">Search result preview</p>
            <p className="truncate text-xs text-muted-foreground">{url}</p>
            <p className="truncate text-base font-medium text-info">{title || 'Untitled page'}</p>
            <p className="line-clamp-2 text-sm text-muted-foreground">
                {description || 'No description yet — search engines will quote an arbitrary sentence from the page.'}
            </p>
        </div>
    );
}

function SocialPreview({ title, description, image, host }: { title: string; description: string; image: string | null; host: string }) {
    return (
        <div className="max-w-md overflow-hidden rounded-lg border border-border">
            <div className="flex aspect-[1200/630] items-center justify-center bg-muted">
                {image ? (
                    <img src={image} alt="" className="size-full object-cover" loading="lazy" />
                ) : (
                    <span className="flex flex-col items-center gap-1 text-xs text-muted-foreground">
                        <ImageOff className="size-6" aria-hidden="true" />
                        No share image
                    </span>
                )}
            </div>
            <div className="space-y-1 bg-card p-3">
                {host && <p className="truncate text-xs text-muted-foreground uppercase">{host}</p>}
                <p className="truncate text-sm font-medium">{title || 'Untitled page'}</p>
                <p className="line-clamp-2 text-xs text-muted-foreground">{description || 'No description yet.'}</p>
            </div>
        </div>
    );
}

const CHECK_ICON: Record<SeoCheckStatus, typeof CircleCheck> = {
    pass: CircleCheck,
    warn: CircleAlert,
    fail: CircleX,
};

const CHECK_TONE: Record<SeoCheckStatus, string> = {
    pass: 'text-success',
    warn: 'text-warning',
    fail: 'text-destructive',
};

function Checklist({ report }: { report: SeoReport }) {
    // Problems first: a list that opens with six passes buries the one thing
    // the author came here to fix.
    const ordered = useMemo(() => {
        const rank: Record<SeoCheckStatus, number> = { fail: 0, warn: 1, pass: 2 };

        return [...report.checks].sort((a, b) => rank[a.status] - rank[b.status]);
    }, [report.checks]);

    return (
        <ul className="space-y-3">
            {ordered.map((entry) => (
                <CheckRow key={entry.key} check={entry} />
            ))}
        </ul>
    );
}

function CheckRow({ check }: { check: SeoCheck }) {
    const Icon = CHECK_ICON[check.status];

    return (
        <li className="flex items-start gap-3">
            <Icon className={cn('mt-0.5 size-4 shrink-0', CHECK_TONE[check.status])} aria-hidden="true" />
            <div className="min-w-0 space-y-0.5">
                <p className="text-sm font-medium">
                    {check.label}
                    <span className="sr-only">: {check.status}</span>
                </p>
                <p className="text-sm text-muted-foreground">{check.message}</p>
            </div>
        </li>
    );
}

export function ScoreBadge({ report }: { report: SeoReport }) {
    const variant = report.failed > 0 ? 'destructive' : report.warnings > 0 ? 'warning' : 'success';

    return (
        <Badge variant={variant}>
            {report.failed > 0 ? `${report.failed} to fix` : report.warnings > 0 ? `${report.warnings} to review` : 'All checks pass'}
        </Badge>
    );
}
