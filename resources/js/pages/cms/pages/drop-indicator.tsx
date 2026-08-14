import { cn } from '@/lib/utils';
import { useDroppable } from '@dnd-kit/core';

/*
|------------------------------------------------------------------------------
| Drop slots
|------------------------------------------------------------------------------
|
| One droppable sits between every pair of blocks, and one at each end, so a
| dragged block type has an unambiguous target for "here, at index N" rather
| than the editor inferring an insertion point from whichever card happens to be
| nearest. The slots stay a few pixels tall until a drag starts, then open up so
| they are easy to hit.
|
*/

export const SLOT_PREFIX = 'slot:';

export function isSlotId(id: string): boolean {
    return id.startsWith(SLOT_PREFIX);
}

export function slotIndex(id: string): number {
    return Number.parseInt(id.slice(SLOT_PREFIX.length), 10);
}

export interface DropSlotProps {
    index: number;
    /** True while any drag is in flight, which is when the slot becomes a target. */
    active: boolean;
}

export function DropSlot({ index, active }: DropSlotProps) {
    const { setNodeRef, isOver } = useDroppable({ id: `${SLOT_PREFIX}${index}`, disabled: !active });

    return (
        <li
            ref={setNodeRef}
            aria-hidden="true"
            className={cn('relative -my-px flex items-center transition-all', active ? 'h-6' : 'h-1.5')}
        >
            <span
                className={cn(
                    'h-0.5 w-full rounded-full transition-all',
                    isOver ? 'bg-primary' : active ? 'bg-border' : 'bg-transparent',
                )}
            />

            {isOver && (
                <span className="absolute left-1/2 -translate-x-1/2 rounded-full bg-primary px-2 py-0.5 text-[10px] font-medium text-primary-foreground shadow-sm">
                    Drop here
                </span>
            )}
        </li>
    );
}
