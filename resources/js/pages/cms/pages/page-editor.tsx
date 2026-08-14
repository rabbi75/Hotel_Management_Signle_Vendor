import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { useAutosave } from '@/components/forms/use-autosave';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toast } from '@/components/ui/toast';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, SharedProps } from '@/types';
import type { BlockData, PageBlockRow, PageEditorProps, PageSeo, RenderedBlock } from '@/types/cms';
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    pointerWithin,
    useSensor,
    useSensors,
    type Announcements,
    type CollisionDetection,
    type DragEndEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { Link, router, usePage } from '@inertiajs/react';
import { CircleAlert, ExternalLink, Eye, LayoutTemplate, Monitor, Send, Smartphone, Tablet, Undo2 } from 'lucide-react';
import { Fragment, useMemo, useRef, useState } from 'react';
import { RenderedBlocks } from '../block-renderer';
import { BlockCard } from './block-card';
import { BlockPalette, isPaletteId, paletteType } from './block-palette';
import { DropSlot, isSlotId, slotIndex } from './drop-indicator';
import { SeoPanel } from './seo-panel';
import { usePanel } from './use-panel';

/** Radix Select cannot hold an empty string value, so "none" needs a sentinel. */
const NONE = '__none__';

/** Preview frame widths, so a layout can be checked without leaving the editor. */
const DEVICES = {
    desktop: { label: 'Desktop', icon: Monitor, width: 'max-w-none' },
    tablet: { label: 'Tablet', icon: Tablet, width: 'max-w-3xl' },
    mobile: { label: 'Mobile', icon: Smartphone, width: 'max-w-sm' },
} as const;

type Device = keyof typeof DEVICES;

interface PageSettings {
    title: string;
    slug: string;
    parent_id: string;
    layout: string;
    is_homepage: boolean;
    seo: PageSeo;
}

function toRendered(blocks: PageBlockRow[]): RenderedBlock[] {
    return blocks.map((block) => ({
        id: block.id,
        type: block.type,
        order: block.order,
        is_visible: block.is_visible,
        data: block.data,
    }));
}

/**
 * Pointer position first, nearest centre as a fallback.
 *
 * `closestCenter` alone is wrong once a drag can start in the palette and end
 * in the canvas: the palette card's centre is nowhere near the slot the cursor
 * is actually over, so it would resolve to whichever slot happens to be closest
 * to the left-hand column.
 */
const collisionDetection: CollisionDetection = (args) => {
    const pointerCollisions = pointerWithin(args);

    return pointerCollisions.length > 0 ? pointerCollisions : closestCenter(args);
};

export default function PageEditor() {
    const { props } = usePage<SharedProps & PageEditorProps>();
    const { page, parents, blockTypes, reservedSlugs, can } = props;

    const { r, may, Layout, home, prefix } = usePanel();
    const confirm = useConfirm();

    const errors = (props.errors ?? {}) as Record<string, string>;
    const editing = page !== undefined;

    const [blocks, setBlocks] = useState<PageBlockRow[]>(page?.blocks ?? []);
    const [showPreview, setShowPreview] = useState(true);
    const [device, setDevice] = useState<Device>('desktop');
    const [scheduleAt, setScheduleAt] = useState('');
    const [dragging, setDragging] = useState<string | null>(null);

    // Kept out of state: it is read only inside the drag handlers, and writing it
    // would re-render the whole canvas on every pointer move.
    const overSlot = useRef<number | null>(null);

    const [settings, setSettings] = useState<PageSettings>({
        title: page?.title ?? '',
        slug: page?.slug ?? '',
        parent_id: String(page?.parent_id ?? ''),
        layout: page?.layout ?? 'default',
        is_homepage: page?.is_homepage ?? false,
        seo: page?.seo ?? {},
    });

    const mayUpdate = may('update');
    const mayPublish = may('publish') && (can?.publish ?? false);

    const schemas = useMemo(() => new Map(blockTypes.map((schema) => [schema.type, schema])), [blockTypes]);

    const slugCollides = reservedSlugs.includes(settings.slug.trim().toLowerCase());

    const autosave = useAutosave<PageSettings>({
        values: settings,
        enabled: editing && mayUpdate && !slugCollides,
        save: (values) =>
            new Promise<void>((resolve, reject) => {
                if (!page) {
                    resolve();

                    return;
                }

                router.patch(
                    r('pages.update', page.id),
                    {
                        title: values.title,
                        slug: values.slug,
                        parent_id: values.parent_id === '' ? null : values.parent_id,
                        layout: values.layout,
                        is_homepage: values.is_homepage,
                        seo: values.seo,
                    },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        only: ['page', 'errors'],
                        onSuccess: () => resolve(),
                        onError: () => reject(new Error('Could not save the page.')),
                    },
                );
            }),
    });

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor),
    );

    function createPage(): void {
        router.post(r('pages.store'), {
            title: settings.title,
            slug: settings.slug || null,
            parent_id: settings.parent_id === '' ? null : settings.parent_id,
            layout: settings.layout,
            is_homepage: settings.is_homepage,
            seo: settings.seo,
        });
    }

    /** Add a block of `type`, at `position` or appended when it is null. */
    function addBlock(type: string, position: number | null = null, data?: BlockData, isVisible = true): void {
        if (!page) {
            return;
        }

        router.post(
            r('pages.blocks.store', page.id),
            {
                type,
                ...(position === null ? {} : { order: position }),
                ...(data ? { data } : {}),
                is_visible: isVisible,
            },
            {
                preserveScroll: true,
                onSuccess: () => router.reload({ only: ['page'] }),
            },
        );
    }

    function saveBlock(block: PageBlockRow, data: BlockData): Promise<void> {
        return new Promise<void>((resolve, reject) => {
            router.patch(
                r('blocks.update', block.id),
                { data },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['errors'],
                    onSuccess: () => resolve(),
                    onError: () => reject(new Error('Could not save this block.')),
                },
            );
        });
    }

    function changeBlock(block: PageBlockRow, data: BlockData): void {
        setBlocks((current) => current.map((entry) => (entry.id === block.id ? { ...entry, data } : entry)));
    }

    function toggleBlock(block: PageBlockRow): void {
        setBlocks((current) => current.map((entry) => (entry.id === block.id ? { ...entry, is_visible: !entry.is_visible } : entry)));
        router.post(r('blocks.toggle', block.id), {}, { preserveScroll: true, preserveState: true, only: ['errors'] });
    }

    function duplicateBlock(block: PageBlockRow): void {
        addBlock(block.type, block.order + 1, block.data, block.is_visible);
    }

    async function deleteBlock(block: PageBlockRow): Promise<void> {
        const ok = await confirm({
            title: `Remove the ${block.label} block?`,
            description: 'Its content is deleted with it.',
            variant: 'destructive',
            confirmLabel: 'Remove block',
        });

        if (!ok) {
            return;
        }

        // Snapshot before the request: an undo has to restore the content and
        // the position, and both are gone once the row is deleted.
        const restore = { type: block.type, data: block.data, order: block.order, visible: block.is_visible };

        setBlocks((current) => current.filter((entry) => entry.id !== block.id));

        router.delete(r('blocks.destroy', block.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${block.label} block removed.`, {
                    action: {
                        label: 'Undo',
                        onClick: () => addBlock(restore.type, restore.order, restore.data, restore.visible),
                    },
                });
            },
        });
    }

    function persistOrder(next: PageBlockRow[]): void {
        if (!page) {
            return;
        }

        setBlocks(next.map((block, index) => ({ ...block, order: index })));

        router.post(
            r('pages.blocks.reorder', page.id),
            { ids: next.map((block) => block.id) },
            { preserveScroll: true, preserveState: true, only: ['errors'] },
        );
    }

    function onDragStart(event: DragStartEvent): void {
        setDragging(String(event.active.id));
        overSlot.current = null;
    }

    function onDragEnd(event: DragEndEvent): void {
        const { active, over } = event;
        const activeId = String(active.id);

        setDragging(null);

        if (!over || !page) {
            return;
        }

        const overId = String(over.id);
        const target = isSlotId(overId) ? slotIndex(overId) : null;

        // A new block dragged out of the palette.
        if (isPaletteId(activeId)) {
            addBlock(paletteType(activeId), target);

            return;
        }

        // An existing block being moved.
        const from = blocks.findIndex((block) => String(block.id) === activeId);

        if (from === -1) {
            return;
        }

        // Dropped on a slot: the index counts positions *between* blocks, so
        // moving downwards has to account for the gap the block leaves behind.
        const to = target === null ? blocks.findIndex((block) => String(block.id) === overId) : target > from ? target - 1 : target;

        if (to === -1 || to === from) {
            return;
        }

        const next = [...blocks];
        const [moved] = next.splice(from, 1);

        if (!moved) {
            return;
        }

        next.splice(to, 0, moved);
        persistOrder(next);
    }

    function openPreview(): void {
        if (!page) {
            return;
        }

        void window.axios
            .post<{ url: string }>(r('pages.preview.create', page.id))
            .then((response) => window.open(response.data.url, '_blank', 'noreferrer'))
            .catch(() => undefined);
    }

    /** What a screen reader hears during a drag. */
    const announcements: Announcements = {
        onDragStart: ({ active }) => {
            const id = String(active.id);

            return isPaletteId(id)
                ? `Picked up a new ${paletteType(id)} block. Move over the page to choose where it goes.`
                : `Picked up the ${blocks.find((block) => String(block.id) === id)?.label ?? ''} block.`;
        },
        onDragOver: ({ over }) => {
            if (!over) {
                return 'No drop position.';
            }

            const id = String(over.id);

            return isSlotId(id) ? `Drop at position ${slotIndex(id) + 1} of ${blocks.length + 1}.` : 'Over a block.';
        },
        onDragEnd: ({ over }) => (over ? 'Dropped.' : 'Cancelled — the block was not moved.'),
        onDragCancel: () => 'Cancelled — the block was not moved.',
    };

    const draggingSchema = dragging && isPaletteId(dragging) ? schemas.get(paletteType(dragging)) : undefined;
    const draggingBlock = dragging && !isPaletteId(dragging) ? blocks.find((block) => String(block.id) === dragging) : undefined;

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl(home) ?? undefined },
        { label: 'Pages', href: routeUrl(`${prefix}pages.index`) ?? undefined },
        { label: editing ? page.title : 'New page' },
    ];

    const statusVariant = page?.status === 'published' ? 'success' : page?.status === 'scheduled' ? 'warning' : 'secondary';

    return (
        <Layout title={editing ? `Edit ${page.title}` : 'New page'} breadcrumbs={breadcrumbs}>
            <DndContext
                sensors={sensors}
                collisionDetection={collisionDetection}
                accessibility={{ announcements }}
                onDragStart={onDragStart}
                onDragEnd={onDragEnd}
                onDragCancel={() => setDragging(null)}
            >
                <div className="space-y-4">
                    {/* Toolbar */}
                    <div className="glass sticky top-14 z-20 -mx-4 flex flex-wrap items-center gap-3 border-b border-border px-4 py-3 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                        <div className="min-w-0 flex-1">
                            <h1 className="truncate text-lg font-semibold tracking-tight text-foreground">
                                {editing ? page.title : 'New page'}
                            </h1>
                            <p className="truncate text-xs text-muted-foreground">
                                {editing ? page.path : 'Give the page a name, then build it from blocks.'}
                            </p>
                        </div>

                        {editing && (
                            <>
                                <span className="hidden text-xs text-muted-foreground sm:inline" role="status" aria-live="polite">
                                    {autosave.message}
                                </span>

                                <Badge variant={statusVariant}>{page.status_label}</Badge>

                                <Button type="button" variant="outline" size="sm" onClick={openPreview}>
                                    <Eye className="size-4" aria-hidden="true" />
                                    Preview
                                </Button>

                                {page.is_public && (
                                    <Button asChild variant="outline" size="sm">
                                        <a href={page.path} target="_blank" rel="noreferrer">
                                            <ExternalLink className="size-4" aria-hidden="true" />
                                            View live
                                        </a>
                                    </Button>
                                )}

                                {mayPublish &&
                                    (page.status === 'published' ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.delete(r('pages.unpublish', page.id), { preserveScroll: true })}
                                        >
                                            <Undo2 className="size-4" aria-hidden="true" />
                                            Unpublish
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() => router.post(r('pages.publish', page.id), {}, { preserveScroll: true })}
                                        >
                                            <Send className="size-4" aria-hidden="true" />
                                            Publish
                                        </Button>
                                    ))}
                            </>
                        )}

                        {!editing && (
                            <Button type="button" onClick={createPage} disabled={settings.title.trim() === ''}>
                                Create page
                            </Button>
                        )}
                    </div>

                    {Object.keys(errors).length > 0 && (
                        <Alert variant="destructive">
                            <CircleAlert className="size-4" aria-hidden="true" />
                            <AlertTitle>This page could not be saved</AlertTitle>
                            <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                        </Alert>
                    )}

                    {!editing ? (
                        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                            <Alert>
                                <CircleAlert className="size-4" aria-hidden="true" />
                                <AlertTitle>Blocks come next</AlertTitle>
                                <AlertDescription>Create the page first; the builder opens straight afterwards.</AlertDescription>
                            </Alert>

                            <aside>
                                <DetailsPanel
                                    settings={settings}
                                    setSettings={setSettings}
                                    parents={parents}
                                    errors={errors}
                                    slugCollides={slugCollides}
                                />
                            </aside>
                        </div>
                    ) : (
                        <div className="grid items-start gap-5 xl:grid-cols-[14rem_minmax(0,1fr)_20rem]">
                            {/* Palette */}
                            <aside className="xl:sticky xl:top-32">
                                <BlockPalette blockTypes={blockTypes} onAdd={(type) => addBlock(type)} disabled={!mayUpdate} />
                            </aside>

                            {/* Canvas */}
                            <section aria-labelledby="blocks-heading" className="min-w-0 space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h2 id="blocks-heading" className="text-sm font-semibold text-foreground">
                                        Page content
                                    </h2>

                                    <Button type="button" size="sm" variant="ghost" onClick={() => setShowPreview((current) => !current)}>
                                        <Eye className="size-4" aria-hidden="true" />
                                        {showPreview ? 'Hide preview' : 'Show preview'}
                                    </Button>
                                </div>

                                {blocks.length === 0 ? (
                                    <div className={cn('rounded-xl border-2 border-dashed transition-colors', dragging ? 'border-primary bg-primary/5' : 'border-border')}>
                                        <ul>
                                            <DropSlot index={0} active={Boolean(dragging)} />
                                        </ul>
                                        <EmptyState
                                            icon={LayoutTemplate}
                                            title={dragging ? 'Drop to add it here' : 'This page is empty'}
                                            description={
                                                dragging
                                                    ? 'Release to place the block on the page.'
                                                    : 'Drag a block from the left, or press + on one to append it.'
                                            }
                                            className="border-0"
                                        />
                                    </div>
                                ) : (
                                    <SortableContext items={blocks.map((block) => block.id)} strategy={verticalListSortingStrategy}>
                                        <ul>
                                            <DropSlot index={0} active={Boolean(dragging)} />

                                            {/*
                                                Card and slot are siblings, not nested: a slot is an
                                                <li> of its own, and an <li> inside an <li> is invalid
                                                markup that React rejects during hydration.
                                            */}
                                            {blocks.map((block, index) => (
                                                <Fragment key={block.id}>
                                                    <li>
                                                        <BlockCard
                                                            block={block}
                                                            schema={schemas.get(block.type)}
                                                            errors={errors}
                                                            disabled={!mayUpdate}
                                                            onSave={saveBlock}
                                                            onChange={changeBlock}
                                                            onToggle={toggleBlock}
                                                            onDuplicate={duplicateBlock}
                                                            onDelete={(entry) => void deleteBlock(entry)}
                                                        />
                                                    </li>
                                                    <DropSlot index={index + 1} active={Boolean(dragging)} />
                                                </Fragment>
                                            ))}
                                        </ul>
                                    </SortableContext>
                                )}

                                {showPreview && (
                                    <Card className="overflow-hidden">
                                        <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0">
                                            <div>
                                                <CardTitle className="text-sm">Live preview</CardTitle>
                                                <CardDescription>Hidden blocks are dimmed; nobody else sees this.</CardDescription>
                                            </div>

                                            <ToggleGroup
                                                type="single"
                                                value={device}
                                                onValueChange={(next) => next && setDevice(next as Device)}
                                                aria-label="Preview width"
                                            >
                                                {(Object.keys(DEVICES) as Device[]).map((key) => {
                                                    const DeviceIcon = DEVICES[key].icon;

                                                    return (
                                                        <Tooltip key={key}>
                                                            <TooltipTrigger asChild>
                                                                <ToggleGroupItem value={key} aria-label={DEVICES[key].label}>
                                                                    <DeviceIcon className="size-4" aria-hidden="true" />
                                                                </ToggleGroupItem>
                                                            </TooltipTrigger>
                                                            <TooltipContent>{DEVICES[key].label}</TooltipContent>
                                                        </Tooltip>
                                                    );
                                                })}
                                            </ToggleGroup>
                                        </CardHeader>

                                        <CardContent className="p-0">
                                            <div className="max-h-[36rem] overflow-y-auto border-t border-border bg-background">
                                                <div className={cn('mx-auto transition-[max-width]', DEVICES[device].width)}>
                                                    <RenderedBlocks blocks={toRendered(blocks)} showHiddenMarkers />
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}

                                <Button asChild variant="ghost" className="w-full">
                                    <Link href={r('pages.index')}>Back to pages</Link>
                                </Button>
                            </section>

                            {/* Inspector */}
                            <aside className="xl:sticky xl:top-32">
                                <Tabs defaultValue="details">
                                    <TabsList className="w-full">
                                        <TabsTrigger value="details" className="flex-1">
                                            Details
                                        </TabsTrigger>
                                        <TabsTrigger value="seo" className="flex-1">
                                            SEO
                                        </TabsTrigger>
                                    </TabsList>

                                    <TabsContent value="details" className="mt-4 space-y-4">
                                        <DetailsPanel
                                            settings={settings}
                                            setSettings={setSettings}
                                            parents={parents}
                                            errors={errors}
                                            slugCollides={slugCollides}
                                        />

                                        {mayPublish && (
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle className="text-sm">Schedule</CardTitle>
                                                    <CardDescription>Publish automatically at a chosen time.</CardDescription>
                                                </CardHeader>
                                                <CardContent className="space-y-2">
                                                    <Label htmlFor="page-schedule" className="sr-only">
                                                        Schedule publication
                                                    </Label>
                                                    <Input
                                                        id="page-schedule"
                                                        type="datetime-local"
                                                        value={scheduleAt}
                                                        onChange={(event) => setScheduleAt(event.target.value)}
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="w-full"
                                                        disabled={scheduleAt === ''}
                                                        onClick={() =>
                                                            router.post(
                                                                r('pages.schedule', page.id),
                                                                { published_at: scheduleAt },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Schedule
                                                    </Button>
                                                    {errors.published_at && (
                                                        <p className="text-sm text-destructive">{errors.published_at}</p>
                                                    )}
                                                </CardContent>
                                            </Card>
                                        )}
                                    </TabsContent>

                                    <TabsContent value="seo" className="mt-4">
                                        <SeoPanel
                                            seo={settings.seo}
                                            onChange={(seo) => setSettings((current) => ({ ...current, seo }))}
                                            errors={errors}
                                            fallbackTitle={settings.title || 'Untitled page'}
                                        />
                                    </TabsContent>
                                </Tabs>
                            </aside>
                        </div>
                    )}
                </div>

                {/*
                    The dragged item follows the cursor in a portal, so it is never
                    clipped by the palette's or the canvas's scroll container.
                */}
                <DragOverlay dropAnimation={null}>
                    {draggingSchema && (
                        <div className="rounded-lg border border-primary bg-card px-3 py-2 text-sm font-medium shadow-lg">
                            {draggingSchema.label}
                        </div>
                    )}
                    {draggingBlock && (
                        <div className="rounded-lg border border-primary bg-card px-3 py-2 text-sm font-medium shadow-lg">
                            {draggingBlock.label}
                        </div>
                    )}
                </DragOverlay>
            </DndContext>
        </Layout>
    );
}

/**
 * Title, address and position in the tree. Extracted because the create screen
 * and the inspector show exactly the same controls.
 */
function DetailsPanel({
    settings,
    setSettings,
    parents,
    errors,
    slugCollides,
}: {
    settings: PageSettings;
    setSettings: React.Dispatch<React.SetStateAction<PageSettings>>;
    parents: Record<string, string>;
    errors: Record<string, string>;
    slugCollides: boolean;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">Details</CardTitle>
                <CardDescription>The name, address and position of this page.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="page-title">
                        Title
                        <span className="text-destructive" aria-hidden="true">
                            *
                        </span>
                    </Label>
                    <Input
                        id="page-title"
                        value={settings.title}
                        aria-invalid={errors.title ? true : undefined}
                        onChange={(event) => setSettings((current) => ({ ...current, title: event.target.value }))}
                    />
                    {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="page-slug">Slug</Label>
                    <Input
                        id="page-slug"
                        value={settings.slug}
                        placeholder="derived-from-the-title"
                        aria-invalid={errors.slug || slugCollides ? true : undefined}
                        onChange={(event) => setSettings((current) => ({ ...current, slug: event.target.value }))}
                    />
                    {slugCollides ? (
                        <p className="text-sm text-destructive">
                            “{settings.slug}” is reserved by the application and cannot be used as a page address.
                        </p>
                    ) : errors.slug ? (
                        <p className="text-sm text-destructive">{errors.slug}</p>
                    ) : (
                        <p className="text-xs text-muted-foreground">Leave empty to generate one from the title.</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="page-parent">Parent page</Label>
                    <Select
                        value={settings.parent_id === '' ? NONE : settings.parent_id}
                        onValueChange={(value) => setSettings((current) => ({ ...current, parent_id: value === NONE ? '' : value }))}
                    >
                        <SelectTrigger id="page-parent">
                            <SelectValue placeholder="No parent" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>No parent (top level)</SelectItem>
                            {Object.entries(parents).map(([id, title]) => (
                                <SelectItem key={id} value={id}>
                                    {title}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <Label htmlFor="page-homepage">Use as the homepage</Label>
                    <Switch
                        id="page-homepage"
                        checked={settings.is_homepage}
                        onCheckedChange={(checked) => setSettings((current) => ({ ...current, is_homepage: checked }))}
                    />
                </div>
            </CardContent>
        </Card>
    );
}
