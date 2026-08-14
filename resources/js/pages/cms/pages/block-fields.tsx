import { MediaPickerDialog } from '@/components/forms/media-picker-dialog';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { RichTextEditor } from '@/pages/blog/posts/rich-text-editor';
import type { BlockData, BlockFieldSchema, BlockRepeaterRow, BlockValue } from '@/types/cms';
import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { restrictToParentElement, restrictToVerticalAxis } from '@dnd-kit/modifiers';
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, CircleAlert, GripVertical, ImageIcon, Plus, Trash2 } from 'lucide-react';
import { useId, useState } from 'react';

/**
 * Renders the editing controls for one block straight from its PHP schema, so
 * a new block type needs no form code of its own.
 */
export interface BlockFieldsProps {
    fields: BlockFieldSchema[];
    data: BlockData;
    onChange: (data: BlockData) => void;
    /** Errors keyed as the server sends them, e.g. `data.heading`. */
    errors: Record<string, string>;
    /** Prefix for error lookups; repeaters extend it with the row index. */
    errorPrefix?: string;
}

function asString(value: BlockValue | undefined): string {
    if (typeof value === 'string') {
        return value;
    }

    return typeof value === 'number' ? String(value) : '';
}

function FieldError({ id, message }: { id: string; message: string | undefined }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className="flex items-start gap-1.5 text-sm text-destructive">
            <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            {message}
        </p>
    );
}

/*
| Repeater rows
|
| Rows are identified by position, not by a stored id — the schema has no place
| to keep one — so a drag reorders the array and React re-keys from the new
| order. The ↑/↓ buttons stay: they are the keyboard path, and a drag handle
| alone would put reordering out of reach without a pointer.
*/

interface RepeaterRowProps {
    id: string;
    index: number;
    label: string;
    children: React.ReactNode;
    onMove: (index: number, delta: number) => void;
    onRemove: (index: number) => void;
    isFirst: boolean;
    isLast: boolean;
}

function RepeaterRow({ id, index, label, children, onMove, onRemove, isFirst, isLast }: RepeaterRowProps) {
    const [open, setOpen] = useState(true);
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn('rounded-md border border-border bg-muted/40', isDragging && 'z-10 opacity-80 shadow-lg')}
        >
            <Collapsible open={open} onOpenChange={setOpen}>
                <div className="flex items-center gap-1 p-2">
                    <button
                        type="button"
                        aria-label={`Reorder ${label} ${index + 1}`}
                        className="cursor-grab rounded p-1 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="size-4" aria-hidden="true" />
                    </button>

                    <CollapsibleTrigger asChild>
                        <button type="button" className="flex min-w-0 flex-1 items-center gap-1.5 rounded text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                            <span className="truncate text-xs font-medium text-muted-foreground">
                                {label} {index + 1}
                            </span>
                            <ChevronDown className={cn('size-3.5 shrink-0 text-muted-foreground transition-transform', open && 'rotate-180')} aria-hidden="true" />
                        </button>
                    </CollapsibleTrigger>

                    <div className="flex shrink-0 gap-0.5">
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            disabled={isFirst}
                            onClick={() => onMove(index, -1)}
                            aria-label={`Move ${label} ${index + 1} up`}
                        >
                            <span aria-hidden="true">↑</span>
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            disabled={isLast}
                            onClick={() => onMove(index, 1)}
                            aria-label={`Move ${label} ${index + 1} down`}
                        >
                            <span aria-hidden="true">↓</span>
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            onClick={() => onRemove(index)}
                            aria-label={`Remove ${label} ${index + 1}`}
                        >
                            <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                        </Button>
                    </div>
                </div>

                <CollapsibleContent>
                    <div className="border-t border-border/60 p-3">{children}</div>
                </CollapsibleContent>
            </Collapsible>
        </li>
    );
}

function ImageField({
    id,
    value,
    label,
    invalid,
    describedBy,
    onChange,
}: {
    id: string;
    value: string;
    label: string;
    invalid: boolean;
    describedBy: string | undefined;
    onChange: (url: string) => void;
}) {
    const [picking, setPicking] = useState(false);

    return (
        <>
            <div className="flex gap-2">
                <Input
                    id={id}
                    type="url"
                    value={value}
                    placeholder="https://…"
                    aria-invalid={invalid ? true : undefined}
                    aria-describedby={describedBy}
                    onChange={(event) => onChange(event.target.value)}
                />
                <Button type="button" variant="outline" onClick={() => setPicking(true)}>
                    <ImageIcon className="size-4" aria-hidden="true" />
                    Browse
                </Button>
            </div>

            {value && (
                <img
                    src={value}
                    alt=""
                    className="mt-2 h-24 w-full rounded-md border border-border object-cover"
                    onError={(event) => {
                        event.currentTarget.style.display = 'none';
                    }}
                />
            )}

            <MediaPickerDialog
                open={picking}
                onOpenChange={setPicking}
                title={`Choose ${label.toLowerCase()}`}
                onSelect={(asset) => onChange(asset.url ?? '')}
            />
        </>
    );
}

export function BlockFields({ fields, data, onChange, errors, errorPrefix = 'data' }: BlockFieldsProps) {
    const scope = useId();

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    function set(name: string, value: BlockValue): void {
        onChange({ ...data, [name]: value });
    }

    return (
        <div className="space-y-4">
            {fields.map((field) => {
                const id = `${scope}-${field.name}`;
                const errorId = `${id}-error`;
                const error = errors[`${errorPrefix}.${field.name}`];
                const describedBy = error ? errorId : field.help ? `${id}-help` : undefined;
                const value = data[field.name];

                if (field.type === 'repeater') {
                    const rows: BlockRepeaterRow[] = Array.isArray(value)
                        ? value.filter(
                              (entry): entry is BlockRepeaterRow => typeof entry === 'object' && entry !== null && !Array.isArray(entry),
                          )
                        : [];

                    const updateRow = (index: number, next: BlockData): void =>
                        set(
                            field.name,
                            rows.map((row, position) => (position === index ? next : row)),
                        );

                    const reorder = (from: number, to: number): void => {
                        if (from === to || from < 0 || to < 0 || from >= rows.length || to >= rows.length) {
                            return;
                        }

                        const next = [...rows];
                        const [moved] = next.splice(from, 1);

                        if (moved) {
                            next.splice(to, 0, moved);
                            set(field.name, next);
                        }
                    };

                    const ids = rows.map((_, index) => `${field.name}-${index}`);

                    return (
                        <fieldset key={field.name} className="space-y-3 rounded-lg border border-border p-3">
                            <legend className="px-1 text-sm font-medium text-foreground">{field.label}</legend>

                            {rows.length === 0 && <p className="text-sm text-muted-foreground">Nothing added yet.</p>}

                            <DndContext
                                sensors={sensors}
                                collisionDetection={closestCenter}
                                modifiers={[restrictToVerticalAxis, restrictToParentElement]}
                                onDragEnd={(event: DragEndEvent) => {
                                    const { active, over } = event;

                                    if (over && active.id !== over.id) {
                                        reorder(ids.indexOf(String(active.id)), ids.indexOf(String(over.id)));
                                    }
                                }}
                            >
                                <SortableContext items={ids} strategy={verticalListSortingStrategy}>
                                    <ul className="space-y-2">
                                        {rows.map((row, index) => (
                                            <RepeaterRow
                                                key={ids[index]}
                                                id={ids[index] ?? String(index)}
                                                index={index}
                                                label={field.label}
                                                isFirst={index === 0}
                                                isLast={index === rows.length - 1}
                                                onMove={(from, delta) => reorder(from, from + delta)}
                                                onRemove={(position) =>
                                                    set(
                                                        field.name,
                                                        rows.filter((_, entry) => entry !== position),
                                                    )
                                                }
                                            >
                                                <BlockFields
                                                    fields={field.fields}
                                                    data={row}
                                                    onChange={(next) => updateRow(index, next)}
                                                    errors={errors}
                                                    errorPrefix={`${errorPrefix}.${field.name}.${index}`}
                                                />
                                            </RepeaterRow>
                                        ))}
                                    </ul>
                                </SortableContext>
                            </DndContext>

                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    const blank: BlockData = {};
                                    field.fields.forEach((child) => {
                                        blank[child.name] = child.default;
                                    });
                                    set(field.name, [...rows, blank]);
                                }}
                            >
                                <Plus className="size-4" aria-hidden="true" />
                                Add {field.label.toLowerCase()}
                            </Button>
                        </fieldset>
                    );
                }

                return (
                    <div key={field.name} className="space-y-2">
                        {field.type === 'boolean' ? (
                            <div className="flex items-center justify-between gap-3">
                                <Label htmlFor={id}>{field.label}</Label>
                                <Switch id={id} checked={value === true} onCheckedChange={(checked) => set(field.name, checked)} />
                            </div>
                        ) : (
                            <>
                                <Label htmlFor={id}>
                                    {field.label}
                                    {field.required && (
                                        <span className="text-destructive" aria-hidden="true">
                                            *
                                        </span>
                                    )}
                                </Label>

                                {field.type === 'select' ? (
                                    <Select value={asString(value) || undefined} onValueChange={(next) => set(field.name, next)}>
                                        <SelectTrigger id={id} aria-invalid={error ? true : undefined} aria-describedby={describedBy}>
                                            <SelectValue placeholder="Choose one" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {field.options.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : field.type === 'richtext' ? (
                                    <RichTextEditor
                                        id={id}
                                        value={asString(value)}
                                        placeholder={field.placeholder ?? undefined}
                                        aria-describedby={describedBy}
                                        onChange={(html) => set(field.name, html)}
                                    />
                                ) : field.type === 'textarea' ? (
                                    <Textarea
                                        id={id}
                                        rows={3}
                                        value={asString(value)}
                                        placeholder={field.placeholder ?? undefined}
                                        aria-invalid={error ? true : undefined}
                                        aria-describedby={describedBy}
                                        onChange={(event) => set(field.name, event.target.value)}
                                    />
                                ) : field.type === 'image' ? (
                                    <ImageField
                                        id={id}
                                        label={field.label}
                                        value={asString(value)}
                                        invalid={Boolean(error)}
                                        describedBy={describedBy}
                                        onChange={(url) => set(field.name, url)}
                                    />
                                ) : (
                                    <Input
                                        id={id}
                                        type={field.type === 'number' ? 'number' : field.type === 'url' ? 'url' : 'text'}
                                        value={asString(value)}
                                        placeholder={field.placeholder ?? undefined}
                                        aria-invalid={error ? true : undefined}
                                        aria-describedby={describedBy}
                                        onChange={(event) => set(field.name, event.target.value)}
                                    />
                                )}
                            </>
                        )}

                        {field.help && !error && (
                            <p id={`${id}-help`} className="text-xs text-muted-foreground">
                                {field.help}
                            </p>
                        )}
                        <FieldError id={errorId} message={error} />
                    </div>
                );
            })}
        </div>
    );
}
