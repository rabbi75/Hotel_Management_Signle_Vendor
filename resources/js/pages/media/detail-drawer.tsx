import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { MediaAsset, MediaFolder } from '@/types/media';
import { useForm } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { CircleAlert, Copy, Crop, Download, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AssetPreview } from './asset-preview';
import { useMediaPanel } from './use-panel';

/** Radix Select cannot hold an empty string value, so "no folder" needs a sentinel. */
const ROOT = '__root__';

export interface DetailDrawerProps {
    asset: MediaAsset | null;
    folders: MediaFolder[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDelete: (asset: MediaAsset) => void;
    onEdit: (asset: MediaAsset) => void;
    canUpdate: boolean;
    canDelete: boolean;
}

interface MetadataValues {
    name: string;
    title: string;
    alt: string;
    caption: string;
    tags: string[];
    folder_id: string;
    [key: string]: string | string[];
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PPp') : '—';
}

export function DetailDrawer({ asset, folders, open, onOpenChange, onDelete, onEdit, canUpdate, canDelete }: DetailDrawerProps) {
    const { r } = useMediaPanel();
    const [copied, setCopied] = useState(false);

    const form = useForm<MetadataValues>({
        name: '',
        title: '',
        alt: '',
        caption: '',
        tags: [],
        folder_id: '',
    });

    const { setData, clearErrors } = form;

    // Reset to the newly selected asset rather than carrying the previous
    // asset's unsaved edits into a different file.
    useEffect(() => {
        if (!asset) {
            return;
        }

        clearErrors();
        setData({
            name: asset.name,
            title: asset.title ?? '',
            alt: asset.alt ?? '',
            caption: asset.caption ?? '',
            tags: asset.tags,
            folder_id: asset.folder_id === null ? '' : String(asset.folder_id),
        });
    }, [asset, clearErrors, setData]);

    if (!asset) {
        return null;
    }

    function save(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!asset) {
            return;
        }

        form.transform((values) => ({
            ...values,
            folder_id: values.folder_id === '' ? null : values.folder_id,
        }));

        form.put(r('update', asset.id), { preserveScroll: true });
    }

    async function copyUrl(): Promise<void> {
        if (!asset?.url) {
            return;
        }

        try {
            await navigator.clipboard.writeText(new URL(asset.url, window.location.origin).toString());
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-md">
                <SheetHeader className="border-b border-border p-4">
                    <SheetTitle className="truncate">{asset.name}</SheetTitle>
                    <SheetDescription>
                        {asset.type_label} · {asset.size_human}
                        {asset.width && asset.height ? ` · ${asset.width}×${asset.height}` : ''}
                    </SheetDescription>
                </SheetHeader>

                <ScrollArea className="min-h-0 flex-1">
                    <div className="space-y-6 p-4">
                        <AssetPreview asset={asset} />

                        <div className="flex flex-wrap gap-2">
                            {asset.url && (
                                <Button asChild variant="outline" size="sm">
                                    <a href={asset.url} download>
                                        <Download className="size-4" aria-hidden="true" />
                                        Download
                                    </a>
                                </Button>
                            )}
                            <Button variant="outline" size="sm" onClick={() => void copyUrl()} disabled={!asset.url}>
                                <Copy className="size-4" aria-hidden="true" />
                                {copied ? 'Copied' : 'Copy URL'}
                            </Button>
                            {canUpdate && asset.type === 'image' && (
                                <Button variant="outline" size="sm" onClick={() => onEdit(asset)}>
                                    <Crop className="size-4" aria-hidden="true" />
                                    Edit image
                                </Button>
                            )}
                            {canDelete && (
                                <Button variant="ghost" size="sm" onClick={() => onDelete(asset)}>
                                    <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                                    Delete
                                </Button>
                            )}
                        </div>

                        <Separator />

                        <form noValidate onSubmit={save} className="space-y-4">
                            <fieldset disabled={!canUpdate} className="space-y-4">
                                <legend className="sr-only">File metadata</legend>

                                <div className="space-y-2">
                                    <Label htmlFor="asset-name">Name</Label>
                                    <Input
                                        id="asset-name"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        aria-invalid={form.errors.name ? true : undefined}
                                    />
                                    <FieldError message={form.errors.name} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="asset-alt">Alt text</Label>
                                    <Input
                                        id="asset-alt"
                                        value={form.data.alt}
                                        onChange={(event) => form.setData('alt', event.target.value)}
                                        aria-describedby="asset-alt-hint"
                                        aria-invalid={form.errors.alt ? true : undefined}
                                    />
                                    <p id="asset-alt-hint" className="text-xs text-muted-foreground">
                                        What the image conveys, for anyone who cannot see it.
                                    </p>
                                    <FieldError message={form.errors.alt} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="asset-title">Title</Label>
                                    <Input
                                        id="asset-title"
                                        value={form.data.title}
                                        onChange={(event) => form.setData('title', event.target.value)}
                                        aria-invalid={form.errors.title ? true : undefined}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="asset-caption">Caption</Label>
                                    <Textarea
                                        id="asset-caption"
                                        rows={3}
                                        value={form.data.caption}
                                        onChange={(event) => form.setData('caption', event.target.value)}
                                        aria-invalid={form.errors.caption ? true : undefined}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="asset-tags">Tags</Label>
                                    <Input
                                        id="asset-tags"
                                        value={form.data.tags.join(', ')}
                                        onChange={(event) =>
                                            form.setData(
                                                'tags',
                                                event.target.value
                                                    .split(',')
                                                    .map((tag) => tag.trim())
                                                    .filter(Boolean),
                                            )
                                        }
                                        aria-describedby="asset-tags-hint"
                                    />
                                    <p id="asset-tags-hint" className="text-xs text-muted-foreground">
                                        Comma separated.
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="asset-folder">Folder</Label>
                                    <Select
                                        value={form.data.folder_id === '' ? ROOT : form.data.folder_id}
                                        onValueChange={(value) => form.setData('folder_id', value === ROOT ? '' : value)}
                                    >
                                        <SelectTrigger id="asset-folder">
                                            <SelectValue placeholder="All files" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ROOT}>All files (no folder)</SelectItem>
                                            {folders.map((folder) => (
                                                <SelectItem key={folder.id} value={String(folder.id)}>
                                                    {folder.path}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {canUpdate && (
                                    <Button type="submit" loading={form.processing} disabled={!form.isDirty || form.processing}>
                                        Save details
                                    </Button>
                                )}
                            </fieldset>
                        </form>

                        <Separator />

                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt className="text-xs text-muted-foreground">Uploaded by</dt>
                                <dd className="truncate">{asset.uploader ?? 'Unknown'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">Uploaded</dt>
                                <dd>{formatDate(asset.created_at)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">Type</dt>
                                <dd className="truncate">{asset.mime_type}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">Size</dt>
                                <dd className="tabular-nums">{asset.size_human}</dd>
                            </div>
                        </dl>

                        {(asset.original_asset_id !== null || (asset.versions_count ?? 0) > 0) && (
                            <div className="space-y-2">
                                <p className="text-xs text-muted-foreground">Versions</p>
                                <div className="flex flex-wrap gap-2">
                                    {asset.original_asset_id !== null && (
                                        <Badge variant="outline">Edited from #{asset.original_asset_id}</Badge>
                                    )}
                                    {(asset.versions_count ?? 0) > 0 && (
                                        <Badge variant="outline">{asset.versions_count} edited version(s)</Badge>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p className="flex items-start gap-1.5 text-sm text-destructive">
            <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            {message}
        </p>
    );
}
