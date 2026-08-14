import { useDebouncedValue } from '@/components/app-shell/use-debounced-value';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { AssetThumbnail } from '@/pages/media/asset-preview';
import type { MediaAsset } from '@/types/media';
import { ImageOff, SearchX } from 'lucide-react';
import { useEffect, useState } from 'react';

/*
|------------------------------------------------------------------------------
| Media picker
|------------------------------------------------------------------------------
|
| A thin browser over the media library, for any form that stores a URL. It
| talks to the `media.picker` endpoint the Media module already exposes for
| exactly this purpose — same query, same tenant scoping and same policy check
| as the manager screen, so the picker can never surface a file the person is
| not allowed to see.
|
| The caller decides what to do with the chosen asset; this component only knows
| how to find one.
|
*/

interface PickerResponse {
    data: MediaAsset[];
}

export interface MediaPickerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (asset: MediaAsset) => void;
    /** Restrict the listing, e.g. `image` for a block's image field. */
    type?: 'image' | 'video' | 'audio' | 'document' | 'other';
    title?: string;
}

export function MediaPickerDialog({ open, onOpenChange, onSelect, type = 'image', title = 'Choose an image' }: MediaPickerDialogProps) {
    const [search, setSearch] = useState('');
    const [assets, setAssets] = useState<MediaAsset[]>([]);
    const [selected, setSelected] = useState<MediaAsset | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    const debouncedSearch = useDebouncedValue(search, 250);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();

        setLoading(true);
        setFailed(false);

        window.axios
            .get<PickerResponse>(route('media.picker'), {
                params: { search: debouncedSearch || undefined, type, per_page: 24 },
                signal: controller.signal,
            })
            .then((response) => setAssets(response.data.data))
            .catch((error: unknown) => {
                // An aborted request is this effect cleaning up after itself, not
                // a failure worth showing anyone.
                if (!controller.signal.aborted) {
                    setFailed(true);
                    void error;
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [debouncedSearch, open, type]);

    useEffect(() => {
        if (!open) {
            setSelected(null);
            setSearch('');
        }
    }, [open]);

    function choose(asset: MediaAsset): void {
        onSelect(asset);
        onOpenChange(false);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85svh] overflow-hidden sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>Pick a file from the media library, or paste a URL instead.</DialogDescription>
                </DialogHeader>

                <Input
                    type="search"
                    value={search}
                    placeholder="Search the library…"
                    aria-label="Search media"
                    onChange={(event) => setSearch(event.target.value)}
                />

                <div className="min-h-64 overflow-y-auto">
                    {loading ? (
                        <div className="flex h-64 items-center justify-center">
                            <Spinner />
                        </div>
                    ) : failed ? (
                        <EmptyState
                            icon={ImageOff}
                            title="The media library could not be reached"
                            description="Paste a URL into the field instead, or try again."
                            className="border-0"
                        />
                    ) : assets.length === 0 ? (
                        <EmptyState
                            icon={SearchX}
                            title={search ? 'Nothing matches that search' : 'The library is empty'}
                            description={search ? 'Try a different term.' : 'Upload a file from the Media screen first.'}
                            className="border-0"
                        />
                    ) : (
                        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {assets.map((asset) => (
                                <li key={asset.id}>
                                    <button
                                        type="button"
                                        onClick={() => setSelected(asset)}
                                        onDoubleClick={() => choose(asset)}
                                        aria-pressed={selected?.id === asset.id}
                                        className={cn(
                                            'w-full overflow-hidden rounded-lg border text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            selected?.id === asset.id ? 'border-primary ring-2 ring-primary/30' : 'border-border hover:border-primary/40',
                                        )}
                                    >
                                        <span className="flex aspect-4/3 items-center justify-center bg-muted">
                                            <AssetThumbnail asset={asset} />
                                        </span>
                                        <span className="block truncate px-2 py-1.5 text-xs text-foreground">{asset.name}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" disabled={selected === null} onClick={() => selected && choose(selected)}>
                        Use this file
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
