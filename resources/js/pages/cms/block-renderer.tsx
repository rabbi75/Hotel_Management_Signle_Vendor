import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { cn } from '@/lib/utils';
import type { RenderedBlock } from '@/types/cms';
import { CircleAlert, EyeOff } from 'lucide-react';
import { blockRenderer } from './blocks/registry';

export interface BlockRendererListProps {
    blocks: RenderedBlock[];
    /** Marks hidden blocks instead of dropping them; used by the editor preview. */
    showHiddenMarkers?: boolean;
    className?: string;
}

export function RenderedBlocks({ blocks, showHiddenMarkers = false, className }: BlockRendererListProps) {
    return (
        <div className={className}>
            {blocks.map((block) => {
                const Renderer = blockRenderer(block.type);

                if (!Renderer) {
                    return (
                        <Alert key={block.id} variant="destructive" className="mx-6 my-4 max-w-2xl">
                            <CircleAlert className="size-4" aria-hidden="true" />
                            <AlertTitle>Unknown block “{block.type}”</AlertTitle>
                            <AlertDescription>No renderer is registered for this block type, so it was skipped.</AlertDescription>
                        </Alert>
                    );
                }

                if (!block.is_visible && !showHiddenMarkers) {
                    return null;
                }

                return (
                    <div key={block.id} className={cn(!block.is_visible && 'relative opacity-50')}>
                        {!block.is_visible && showHiddenMarkers && (
                            <p className="absolute top-2 right-2 z-10 flex items-center gap-1 rounded bg-muted px-2 py-1 text-xs text-muted-foreground">
                                <EyeOff className="size-3" aria-hidden="true" />
                                Hidden
                            </p>
                        )}
                        <Renderer data={block.data} />
                    </div>
                );
            })}
        </div>
    );
}
