import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { PageSeo } from '@/types/cms';
import { useId } from 'react';

const TITLE_MAX = 60;
const DESCRIPTION_MAX = 160;

export interface SeoPanelProps {
    seo: PageSeo;
    onChange: (seo: PageSeo) => void;
    errors: Record<string, string>;
    fallbackTitle: string;
}

export function SeoPanel({ seo, onChange, errors, fallbackTitle }: SeoPanelProps) {
    const scope = useId();
    const titleId = `${scope}-title`;
    const descriptionId = `${scope}-description`;
    const keywordsId = `${scope}-keywords`;
    const canonicalId = `${scope}-canonical`;
    const imageId = `${scope}-image`;
    const noindexId = `${scope}-noindex`;

    const title = seo.title ?? '';
    const description = seo.description ?? '';

    function set<K extends keyof PageSeo>(key: K, value: PageSeo[K]): void {
        onChange({ ...seo, [key]: value });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Search &amp; social</CardTitle>
                <CardDescription>How this page appears in results and link previews.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor={titleId}>Meta title</Label>
                    <Input
                        id={titleId}
                        value={title}
                        placeholder={fallbackTitle}
                        aria-invalid={errors['seo.title'] ? true : undefined}
                        onChange={(event) => set('title', event.target.value)}
                    />
                    <p className={cn('text-xs', title.length > TITLE_MAX ? 'text-destructive' : 'text-muted-foreground')}>
                        {title.length}/{TITLE_MAX} — falls back to the page title when empty.
                    </p>
                </div>

                <div className="space-y-2">
                    <Label htmlFor={descriptionId}>Meta description</Label>
                    <Textarea
                        id={descriptionId}
                        rows={3}
                        value={description}
                        aria-invalid={errors['seo.description'] ? true : undefined}
                        onChange={(event) => set('description', event.target.value)}
                    />
                    <p className={cn('text-xs', description.length > DESCRIPTION_MAX ? 'text-destructive' : 'text-muted-foreground')}>
                        {description.length}/{DESCRIPTION_MAX}
                    </p>
                </div>

                <div className="space-y-2">
                    <Label htmlFor={keywordsId}>Keywords</Label>
                    <Input id={keywordsId} value={seo.keywords ?? ''} onChange={(event) => set('keywords', event.target.value)} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor={imageId}>Social image URL</Label>
                    <Input id={imageId} type="url" value={seo.og_image ?? ''} onChange={(event) => set('og_image', event.target.value)} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor={canonicalId}>Canonical URL</Label>
                    <Input
                        id={canonicalId}
                        type="url"
                        value={seo.canonical ?? ''}
                        onChange={(event) => set('canonical', event.target.value)}
                    />
                </div>

                <div className="flex items-center justify-between gap-3">
                    <Label htmlFor={noindexId}>Hide from search engines</Label>
                    <Switch id={noindexId} checked={seo.noindex === true} onCheckedChange={(checked) => set('noindex', checked)} />
                </div>
            </CardContent>
        </Card>
    );
}
