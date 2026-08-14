import { Icon } from '@/components/app-shell/icon';
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { MenuItemRow, MenuRow, MenusIndexProps } from '@/types/cms';
import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, GripVertical, ListTree, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useCmsMenuPanel } from './use-panel';

const NONE = '__none__';

interface FlatNode {
    item: MenuItemRow;
    depth: number;
}

/** Depth-first flattening: dnd-kit sorts a single list, indentation carries the tree. */
function flatten(items: MenuItemRow[], depth = 0): FlatNode[] {
    return items.flatMap((item) => [{ item, depth }, ...flatten(item.children, depth + 1)]);
}

function SortableMenuItem({
    node,
    canManage,
    onIndent,
    onOutdent,
    onDelete,
    onRename,
}: {
    node: FlatNode;
    canManage: boolean;
    onIndent: (item: MenuItemRow) => void;
    onOutdent: (item: MenuItemRow) => void;
    onDelete: (item: MenuItemRow) => void;
    onRename: (item: MenuItemRow, label: string) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: node.item.id });
    const [label, setLabel] = useState(node.item.label);

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition, marginInlineStart: `${node.depth * 1.5}rem` }}
            className={cn('flex items-center gap-2 rounded-md border border-border bg-card p-2', isDragging && 'z-10 opacity-80 shadow-lg')}
        >
            <button
                type="button"
                className="cursor-grab rounded p-1 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label={`Reorder ${node.item.label}`}
                {...attributes}
                {...listeners}
            >
                <GripVertical className="size-4" aria-hidden="true" />
            </button>

            {node.item.icon && <Icon name={node.item.icon} className="size-4 text-muted-foreground" />}

            <Input
                value={label}
                aria-label={`Label for ${node.item.label}`}
                className="h-8 min-w-0 flex-1"
                disabled={!canManage}
                onChange={(event) => setLabel(event.target.value)}
                onBlur={() => label !== node.item.label && onRename(node.item, label)}
            />

            <span className="hidden max-w-40 truncate font-mono text-xs text-muted-foreground sm:inline">{node.item.resolved_url}</span>

            {node.item.permission && <Badge variant="outline">{node.item.permission}</Badge>}

            {canManage && (
                <div className="flex shrink-0 gap-0.5">
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        aria-label={`Outdent ${node.item.label}`}
                        onClick={() => onOutdent(node.item)}
                    >
                        <ChevronLeft className="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        aria-label={`Indent ${node.item.label}`}
                        onClick={() => onIndent(node.item)}
                    >
                        <ChevronRight className="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        aria-label={`Remove ${node.item.label}`}
                        onClick={() => onDelete(node.item)}
                    >
                        <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                    </Button>
                </div>
            )}
        </li>
    );
}

function MenuBuilder({ menu, pages, canManage }: { menu: MenuRow; pages: Record<string, string>; canManage: boolean }) {
    const { r } = useCmsMenuPanel();
    const confirm = useConfirm();
    const [adding, setAdding] = useState(false);
    const [label, setLabel] = useState('');
    const [pageId, setPageId] = useState(NONE);
    const [url, setUrl] = useState('');
    const [permission, setPermission] = useState('');

    const nodes = useMemo(() => flatten(menu.items), [menu.items]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    function persist(next: FlatNode[]): void {
        router.post(
            r('menus.items.reorder', menu.id),
            {
                items: next.map((node, index) => ({
                    id: node.item.id,
                    parent_id: node.item.parent_id,
                    order: index,
                })),
            },
            { preserveScroll: true },
        );
    }

    function onDragEnd(event: DragEndEvent): void {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const from = nodes.findIndex((node) => node.item.id === active.id);
        const to = nodes.findIndex((node) => node.item.id === over.id);

        if (from === -1 || to === -1) {
            return;
        }

        const next = [...nodes];
        const [moved] = next.splice(from, 1);

        if (!moved) {
            return;
        }

        next.splice(to, 0, moved);
        persist(next);
    }

    function reparent(item: MenuItemRow, parentId: number | null): void {
        router.patch(r('menu-items.update', item.id), { parent_id: parentId }, { preserveScroll: true });
    }

    function indent(item: MenuItemRow): void {
        const index = nodes.findIndex((node) => node.item.id === item.id);
        const previous = nodes[index - 1];

        // Only the sibling immediately above can become a parent; anything else
        // would silently move the item somewhere the user did not point at.
        if (previous && previous.item.id !== item.parent_id) {
            reparent(item, previous.item.id);
        }
    }

    function outdent(item: MenuItemRow): void {
        if (item.parent_id === null) {
            return;
        }

        const parent = nodes.find((node) => node.item.id === item.parent_id);
        reparent(item, parent?.item.parent_id ?? null);
    }

    async function remove(item: MenuItemRow): Promise<void> {
        const ok = await confirm({
            title: `Remove “${item.label}” from this menu?`,
            description: item.children.length > 0 ? 'Its child links are removed too.' : undefined,
            variant: 'destructive',
            confirmLabel: 'Remove',
        });

        if (ok) {
            router.delete(r('menu-items.destroy', item.id), { preserveScroll: true });
        }
    }

    function addItem(): void {
        router.post(
            r('menus.items.store', menu.id),
            {
                label,
                page_id: pageId === NONE ? null : pageId,
                url: pageId === NONE ? url : null,
                permission: permission || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAdding(false);
                    setLabel('');
                    setUrl('');
                    setPageId(NONE);
                    setPermission('');
                },
            },
        );
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0">
                <div className="min-w-0">
                    <CardTitle className="truncate">{menu.name}</CardTitle>
                    <CardDescription>{menu.location_label}</CardDescription>
                </div>
                {canManage && (
                    <Button type="button" size="sm" variant="outline" onClick={() => setAdding(true)}>
                        <Plus className="size-4" aria-hidden="true" />
                        Add link
                    </Button>
                )}
            </CardHeader>

            <CardContent>
                {nodes.length === 0 ? (
                    <EmptyState icon={ListTree} title="No links yet" description="Add a link to build this menu." className="border-0" />
                ) : (
                    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                        <SortableContext items={nodes.map((node) => node.item.id)} strategy={verticalListSortingStrategy}>
                            <ul className="space-y-1.5" aria-label={`${menu.name} links`}>
                                {nodes.map((node) => (
                                    <SortableMenuItem
                                        key={node.item.id}
                                        node={node}
                                        canManage={canManage}
                                        onIndent={indent}
                                        onOutdent={outdent}
                                        onDelete={(item) => void remove(item)}
                                        onRename={(item, next) =>
                                            router.patch(r('menu-items.update', item.id), { label: next }, { preserveScroll: true })
                                        }
                                    />
                                ))}
                            </ul>
                        </SortableContext>
                    </DndContext>
                )}
            </CardContent>

            <Dialog open={adding} onOpenChange={setAdding}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add a link</DialogTitle>
                        <DialogDescription>Point at one of your pages, or at any URL.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor={`label-${menu.id}`}>Label</Label>
                            <Input id={`label-${menu.id}`} value={label} onChange={(event) => setLabel(event.target.value)} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor={`page-${menu.id}`}>Page</Label>
                            <Select value={pageId} onValueChange={setPageId}>
                                <SelectTrigger id={`page-${menu.id}`}>
                                    <SelectValue placeholder="Choose a page" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Use a custom URL</SelectItem>
                                    {Object.entries(pages).map(([id, title]) => (
                                        <SelectItem key={id} value={id}>
                                            {title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {pageId === NONE && (
                            <div className="space-y-2">
                                <Label htmlFor={`url-${menu.id}`}>URL</Label>
                                <Input
                                    id={`url-${menu.id}`}
                                    value={url}
                                    placeholder="https://…"
                                    onChange={(event) => setUrl(event.target.value)}
                                />
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor={`permission-${menu.id}`}>Permission gate</Label>
                            <Input
                                id={`permission-${menu.id}`}
                                value={permission}
                                placeholder="Leave empty to show to everyone"
                                onChange={(event) => setPermission(event.target.value)}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setAdding(false)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={addItem} disabled={label.trim() === '' || (pageId === NONE && url.trim() === '')}>
                            Add link
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

export default function CmsMenusIndex({ menus, locations, pages, can }: MenusIndexProps) {
    const { Layout, home, r, may } = useCmsMenuPanel();
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [location, setLocation] = useState<string>(String(locations[0]?.value ?? 'header'));

    const canManage = may('cms.menus.manage') && can.manage;

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl(home) ?? undefined }, { label: 'Menus' }];

    const used = new Set(menus.map((menu) => menu.location));
    const available = locations.filter((option) => !used.has(String(option.value) as MenuRow['location']));

    function createMenu(): void {
        router.post(
            r('menus.store'),
            { name, location },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCreating(false);
                    setName('');
                },
            },
        );
    }

    return (
        <Layout title="Menus" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Menus"
                    description="The navigation shown on your public site. Drag to reorder, indent to nest."
                    actions={
                        canManage && available.length > 0 ? (
                            <Button type="button" onClick={() => setCreating(true)}>
                                <Plus className="size-4" aria-hidden="true" />
                                New menu
                            </Button>
                        ) : undefined
                    }
                />

                {menus.length === 0 ? (
                    <EmptyState
                        icon={ListTree}
                        title="No menus yet"
                        description="Create a header or footer menu to give visitors a way around."
                        action={
                            canManage ? (
                                <Button type="button" size="sm" onClick={() => setCreating(true)}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New menu
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="grid gap-6 lg:grid-cols-2">
                        {menus.map((menu) => (
                            <MenuBuilder key={menu.id} menu={menu} pages={pages} canManage={canManage} />
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>New menu</DialogTitle>
                        <DialogDescription>Each location can hold one menu.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="menu-name">Name</Label>
                            <Input id="menu-name" value={name} onChange={(event) => setName(event.target.value)} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="menu-location">Location</Label>
                            <Select value={location} onValueChange={setLocation}>
                                <SelectTrigger id="menu-location">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {available.map((option) => (
                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setCreating(false)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={createMenu} disabled={name.trim() === ''}>
                            Create menu
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Layout>
    );
}
