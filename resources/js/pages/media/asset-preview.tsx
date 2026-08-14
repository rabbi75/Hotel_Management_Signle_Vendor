import { cn } from '@/lib/utils';
import type { MediaAsset } from '@/types/media';
import { File, FileAudio, FileText, FileVideo } from 'lucide-react';

const FALLBACK_ICON = {
    video: FileVideo,
    audio: FileAudio,
    document: FileText,
    other: File,
    image: File,
} as const;

/**
 * The visual for one asset.
 *
 * Images render themselves; everything else gets a typed placeholder rather
 * than a broken <img>, which is what a browser shows for a PDF or an MP4.
 */
export function AssetThumbnail({ asset, className }: { asset: MediaAsset; className?: string }) {
    if (asset.type === 'image' && asset.thumb_url) {
        return (
            <img
                src={asset.thumb_url}
                alt={asset.alt ?? ''}
                loading="lazy"
                decoding="async"
                className={cn('size-full object-contain', className)}
            />
        );
    }

    const Icon = FALLBACK_ICON[asset.type] ?? File;

    return (
        <div className={cn('flex size-full flex-col items-center justify-center gap-2 text-muted-foreground', className)}>
            <Icon className="size-8" aria-hidden="true" />
            <span className="text-[0.65rem] font-medium uppercase">{asset.extension}</span>
        </div>
    );
}

/** The large preview in the detail drawer: playable where the browser can play it. */
export function AssetPreview({ asset }: { asset: MediaAsset }) {
    if (!asset.url) {
        return (
            <div className="flex aspect-video items-center justify-center rounded-lg border border-dashed border-border text-sm text-muted-foreground">
                This file is no longer available.
            </div>
        );
    }

    if (asset.type === 'image') {
        return (
            <img
                src={asset.preview_url ?? asset.url}
                alt={asset.alt ?? asset.name}
                className="max-h-[42svh] w-full rounded-lg bg-muted object-contain"
            />
        );
    }

    if (asset.type === 'video') {
        return (
            <video controls preload="metadata" className="max-h-[42svh] w-full rounded-lg bg-muted" aria-label={asset.name}>
                <source src={asset.url} type={asset.mime_type} />
                Your browser cannot play this video.
            </video>
        );
    }

    if (asset.type === 'audio') {
        return (
            <audio controls preload="metadata" className="w-full" aria-label={asset.name}>
                <source src={asset.url} type={asset.mime_type} />
                Your browser cannot play this audio file.
            </audio>
        );
    }

    if (asset.mime_type === 'application/pdf') {
        return <iframe src={asset.url} title={`Preview of ${asset.name}`} className="h-[42svh] w-full rounded-lg border border-border" />;
    }

    return (
        <div className="flex aspect-video flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border">
            <FileText className="size-8 text-muted-foreground" aria-hidden="true" />
            <a href={asset.url} className="text-sm text-primary underline-offset-4 hover:underline" download>
                Download {asset.name}.{asset.extension}
            </a>
        </div>
    );
}
