import { Icon } from '@/components/app-shell/icon';
import { useAutosave } from '@/components/forms/use-autosave';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type { BlockData, BlockSchema, PageBlockRow } from '@/types/cms';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, Copy, Eye, EyeOff, GripVertical, LayoutPanelTop, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BlockFields } from './block-fields';

export interface BlockCardProps {
    block: PageBlockRow;
    schema: BlockSchema | undefined;
    errors: Record<string, string>;
    disabled: boolean;
    onSave: (block: PageBlockRow, data: BlockData) => Promise<void>;
    onToggle: (block: PageBlockRow) => void;
    onDuplicate: (block: PageBlockRow) => void;
    onDelete: (block: PageBlockRow) => void;
    onChange: (block: PageBlockRow, data: BlockData) => void;
}

/**
 * One section of the page, as an editable card.
 *
 * Content fields and layout fields are shown in separate panels: everything a
 * block is about comes first, and the background/spacing/width controls every
 * block shares sit behind a disclosure so they never crowd the copy an author
 * actually came here to write.
 */
export function BlockCard({ block, schema, errors, disabled, onSave, onToggle, onDuplicate, onDelete, onChange }: BlockCardProps) {
    const [open, setOpen] = useState(false);
    const [layoutOpen, setLayoutOpen] = useState(false);
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: block.id });

    const contentFields = useMemo(() => schema?.fields.filter((field) => field.group !== 'section') ?? [], [schema]);
    const sectionFields = useMemo(() => schema?.fields.filter((field) => field.group === 'section') ?? [], [schema]);

    const autosave = useAutosave<BlockData>({
        values: block.data,
        enabled: !disabled,
        save: (values) => onSave(block, values),
    });

    return (
        <Card
            ref={setNodeRef}
            id={`block-${block.id}`}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'overflow-hidden transition-shadow',
                isDragging && 'z-10 opacity-80 shadow-lg',
                !block.is_visible && 'opacity-60',
                open && 'ring-1 ring-primary/20',
            )}
        >
            <Collapsible open={open} onOpenChange={setOpen}>
                <CardHeader className="flex flex-row items-center gap-2 space-y-0 py-3">
                    <button
                        type="button"
                        className="cursor-grab rounded p-1 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
                        aria-label={`Reorder ${block.label} block`}
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="size-4" aria-hidden="true" />
                    </button>

                    <Icon name={block.icon} className="size-4 text-muted-foreground" />

                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="flex min-w-0 flex-1 items-center gap-2 rounded text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <span className="truncate text-sm font-medium text-foreground">{block.label}</span>
                            {!block.is_visible && <span className="shrink-0 text-xs text-muted-foreground">(hidden)</span>}
                            <ChevronDown
                                className={cn('size-4 shrink-0 text-muted-foreground transition-transform', open && 'rotate-180')}
                                aria-hidden="true"
                            />
                        </button>
                    </CollapsibleTrigger>

                    <span className="hidden text-xs text-muted-foreground sm:inline" aria-live="polite">
                        {open ? autosave.message : ''}
                    </span>

                    <div className="flex shrink-0 items-center gap-0.5">
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-8"
                            aria-label={block.is_visible ? `Hide ${block.label} block` : `Show ${block.label} block`}
                            onClick={() => onToggle(block)}
                        >
                            {block.is_visible ? (
                                <Eye className="size-4" aria-hidden="true" />
                            ) : (
                                <EyeOff className="size-4" aria-hidden="true" />
                            )}
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-8"
                            aria-label={`Duplicate ${block.label} block`}
                            onClick={() => onDuplicate(block)}
                        >
                            <Copy className="size-4" aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-8"
                            aria-label={`Delete ${block.label} block`}
                            onClick={() => onDelete(block)}
                        >
                            <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                        </Button>
                    </div>
                </CardHeader>

                <CollapsibleContent>
                    <CardContent className="space-y-4 pt-0 pb-4">
                        {schema ? (
                            <>
                                <BlockFields
                                    fields={contentFields}
                                    data={block.data}
                                    errors={errors}
                                    onChange={(data) => onChange(block, data)}
                                />

                                {sectionFields.length > 0 && (
                                    <Collapsible open={layoutOpen} onOpenChange={setLayoutOpen}>
                                        <CollapsibleTrigger asChild>
                                            <button
                                                type="button"
                                                className="flex w-full items-center gap-2 rounded-md border border-border px-3 py-2 text-left text-sm font-medium text-muted-foreground transition-colors hover:bg-accent/50 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                <LayoutPanelTop className="size-4" aria-hidden="true" />
                                                Layout
                                                <ChevronDown
                                                    className={cn('ml-auto size-4 transition-transform', layoutOpen && 'rotate-180')}
                                                    aria-hidden="true"
                                                />
                                            </button>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <div className="pt-4">
                                                <BlockFields
                                                    fields={sectionFields}
                                                    data={block.data}
                                                    errors={errors}
                                                    onChange={(data) => onChange(block, data)}
                                                />
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                )}
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No schema is registered for “{block.type}”, so this block cannot be edited here.
                            </p>
                        )}
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}
