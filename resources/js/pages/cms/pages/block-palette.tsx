import { Icon } from '@/components/app-shell/icon';
import { cn } from '@/lib/utils';
import type { BlockSchema } from '@/types/cms';
import { useDraggable } from '@dnd-kit/core';
import { Plus } from 'lucide-react';

/*
|------------------------------------------------------------------------------
| The block palette
|------------------------------------------------------------------------------
|
| Every registered block type, as something you can drag onto the page. The
| draggable id is namespaced `palette:<type>` so the editor's drop handler can
| tell "a new block of this type" from "an existing block being moved" by the id
| alone — the two gestures land in the same DndContext.
|
| Each card is also a button: clicking appends the block. Dragging is the richer
| gesture but must never be the only one, for a pointer-less user and for anyone
| who simply wants it at the end.
|
*/

export const PALETTE_PREFIX = 'palette:';

export function isPaletteId(id: string): boolean {
    return id.startsWith(PALETTE_PREFIX);
}

export function paletteType(id: string): string {
    return id.slice(PALETTE_PREFIX.length);
}

function PaletteItem({ schema, onAdd, disabled }: { schema: BlockSchema; onAdd: (type: string) => void; disabled: boolean }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: `${PALETTE_PREFIX}${schema.type}`,
        data: { type: schema.type, source: 'palette' },
        disabled,
    });

    return (
        <li>
            <div
                ref={setNodeRef}
                className={cn(
                    'group relative flex w-full items-start gap-2.5 rounded-lg border border-border bg-card p-2.5 text-left transition-colors',
                    disabled ? 'opacity-60' : 'cursor-grab hover:border-primary/40 hover:bg-accent/50 active:cursor-grabbing',
                    isDragging && 'opacity-40',
                )}
                {...attributes}
                {...listeners}
            >
                <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <Icon name={schema.icon} className="size-4" />
                </span>

                <span className="min-w-0 flex-1">
                    <span className="block text-sm font-medium text-card-foreground">{schema.label}</span>
                    <span className="mt-0.5 line-clamp-2 block text-xs text-pretty text-muted-foreground">{schema.description}</span>
                </span>

                {/*
                    A real button so the block can be added without a drag at all.
                    It sits outside the draggable listeners' path by stopping
                    propagation on pointerdown, which would otherwise start a drag
                    and swallow the click.
                */}
                <button
                    type="button"
                    disabled={disabled}
                    onPointerDown={(event) => event.stopPropagation()}
                    onKeyDown={(event) => event.stopPropagation()}
                    onClick={() => onAdd(schema.type)}
                    aria-label={`Add ${schema.label} block to the end of the page`}
                    className="absolute top-1.5 right-1.5 rounded p-1 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:bg-accent hover:text-foreground focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none"
                >
                    <Plus className="size-3.5" aria-hidden="true" />
                </button>
            </div>
        </li>
    );
}

export interface BlockPaletteProps {
    blockTypes: BlockSchema[];
    onAdd: (type: string) => void;
    disabled?: boolean;
    className?: string;
}

export function BlockPalette({ blockTypes, onAdd, disabled = false, className }: BlockPaletteProps) {
    return (
        <div className={cn('space-y-3', className)}>
            <div>
                <h2 className="text-sm font-semibold text-foreground">Blocks</h2>
                <p className="mt-0.5 text-xs text-muted-foreground">Drag one onto the page, or press + to append it.</p>
            </div>

            <ul className="space-y-2">
                {blockTypes.map((schema) => (
                    <PaletteItem key={schema.type} schema={schema} onAdd={onAdd} disabled={disabled} />
                ))}
            </ul>
        </div>
    );
}
