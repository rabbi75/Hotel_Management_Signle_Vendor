import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { BlogCategory, BlogOption } from '@/types/blog';
import { router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, CircleAlert, Folder, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useBlogPanel } from '../use-panel';

const NONE = '__none__';

interface CategoriesIndexProps {
    table: TablePayload<BlogCategory>;
    tree: BlogCategory[];
    categories: BlogOption[];
    can: { manage: boolean };
}

export default function CategoriesIndex({ table, tree, categories, can }: CategoriesIndexProps) {
    const { Layout, home, r, may } = useBlogPanel();
    const confirm = useConfirm();

    const [editing, setEditing] = useState<BlogCategory | null>(null);
    const [open, setOpen] = useState(false);

    const mayManage = can.manage && may('blog.taxonomy.manage');
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl(home) ?? undefined }, { label: 'Categories' }];

    function openCreate(parent?: BlogCategory): void {
        setEditing(parent ? ({ parent_id: parent.id } as BlogCategory) : null);
        setOpen(true);
    }

    function openEdit(category: BlogCategory): void {
        setEditing(category);
        setOpen(true);
    }

    async function remove(category: BlogCategory): Promise<void> {
        const ok = await confirm({
            title: `Delete the “${category.name}” category?`,
            description:
                (category.posts_count ?? 0) > 0
                    ? `Its ${category.posts_count} posts become uncategorised; nothing is deleted. Subcategories move up a level.`
                    : 'Subcategories move up a level. No posts are deleted.',
            variant: 'destructive',
            confirmLabel: 'Delete category',
        });

        if (ok) {
            router.delete(r('categories.destroy', category.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<BlogCategory> = {
        name: (row) => <span className="block truncate text-sm font-medium">{row.name}</span>,
        slug: (row) => <TextCell value={`/${row.slug}`} muted />,
        parent: (row) => <TextCell value={row.parent} muted />,
        posts_count: (row) => <span className="tabular-nums">{row.posts_count ?? 0}</span>,
        created_at: (row) => <DateCell value={row.created_at} />,
    };

    function rowActions(row: BlogCategory): RowAction[] {
        if (!mayManage) {
            return [];
        }

        return [
            {
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => openEdit(row),
            },
            {
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            },
        ];
    }

    return (
        <Layout title="Categories" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Categories"
                    description="How posts are grouped. A parent category's archive also lists its children's posts."
                    actions={
                        mayManage ? (
                            <Button onClick={() => openCreate()}>
                                <Plus className="size-4" aria-hidden="true" />
                                New category
                            </Button>
                        ) : null
                    }
                />

                <Tabs defaultValue="tree" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="tree">Tree</TabsTrigger>
                        <TabsTrigger value="list">List</TabsTrigger>
                    </TabsList>

                    <TabsContent value="tree">
                        <Card>
                            <CardContent className="py-4">
                                {tree.length === 0 ? (
                                    <EmptyState
                                        icon={Folder}
                                        title="No categories yet"
                                        description="Create a top-level category, then nest as many levels as you need."
                                        action={
                                            mayManage ? (
                                                <Button size="sm" onClick={() => openCreate()}>
                                                    <Plus className="size-4" aria-hidden="true" />
                                                    New category
                                                </Button>
                                            ) : null
                                        }
                                    />
                                ) : (
                                    <ul role="tree" aria-label="Category hierarchy" className="space-y-1">
                                        {tree.map((node) => (
                                            <CategoryBranch
                                                key={node.id}
                                                node={node}
                                                depth={0}
                                                canManage={mayManage}
                                                onEdit={openEdit}
                                                onAddChild={openCreate}
                                                onDelete={(target) => void remove(target)}
                                            />
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="list">
                        <DataTable<BlogCategory>
                            payload={table}
                            propKey="table"
                            name="categories"
                            columns={columns}
                            getRowId={(row) => String(row.id)}
                            rowActions={mayManage ? rowActions : undefined}
                            searchPlaceholder="Search categories…"
                            caption="Blog categories in this workspace"
                            emptyState={
                                <EmptyState
                                    icon={filtered ? SearchX : Folder}
                                    title={filtered ? 'No categories match your search' : 'No categories yet'}
                                    description={
                                        filtered ? 'Clear the search to see the whole list.' : 'Create one to start grouping posts.'
                                    }
                                    className="border-0"
                                />
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>

            <CategoryDialog
                open={open}
                onOpenChange={setOpen}
                category={editing}
                categories={categories}
                key={editing?.id ?? editing?.parent_id ?? 'new'}
            />
        </Layout>
    );
}

interface BranchProps {
    node: BlogCategory;
    depth: number;
    canManage: boolean;
    onEdit: (node: BlogCategory) => void;
    onAddChild: (node: BlogCategory) => void;
    onDelete: (node: BlogCategory) => void;
}

function CategoryBranch({ node, depth, canManage, onEdit, onAddChild, onDelete }: BranchProps) {
    const [expanded, setExpanded] = useState(true);
    const hasChildren = node.children.length > 0;

    return (
        <li role="treeitem" aria-expanded={hasChildren ? expanded : undefined} className="min-w-0">
            <div
                className="flex flex-wrap items-center gap-2 rounded-md px-2 py-2 hover:bg-muted/50"
                style={{ paddingInlineStart: `${depth * 1.25}rem` }}
            >
                {hasChildren ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => setExpanded((current) => !current)}
                        aria-label={expanded ? `Collapse ${node.name}` : `Expand ${node.name}`}
                    >
                        {expanded ? (
                            <ChevronDown className="size-4" aria-hidden="true" />
                        ) : (
                            <ChevronRight className="size-4" aria-hidden="true" />
                        )}
                    </Button>
                ) : (
                    <span className="size-8" aria-hidden="true" />
                )}

                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">{node.name}</span>
                    <span className="block truncate text-xs text-muted-foreground">/{node.slug}</span>
                </span>

                {(node.posts_count ?? 0) > 0 && <Badge variant="outline">{node.posts_count} posts</Badge>}

                {canManage && (
                    <span className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => onAddChild(node)}
                            aria-label={`Add a subcategory under ${node.name}`}
                        >
                            <Plus className="size-4" aria-hidden="true" />
                        </Button>
                        <Button variant="ghost" size="icon-sm" onClick={() => onEdit(node)} aria-label={`Edit ${node.name}`}>
                            <Pencil className="size-4" aria-hidden="true" />
                        </Button>
                        <Button variant="ghost" size="icon-sm" onClick={() => onDelete(node)} aria-label={`Delete ${node.name}`}>
                            <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                        </Button>
                    </span>
                )}
            </div>

            {hasChildren && expanded && (
                <ul role="group" className="space-y-1">
                    {node.children.map((child) => (
                        <CategoryBranch
                            key={child.id}
                            node={child}
                            depth={depth + 1}
                            canManage={canManage}
                            onEdit={onEdit}
                            onAddChild={onAddChild}
                            onDelete={onDelete}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

interface CategoryFormValues {
    name: string;
    slug: string;
    description: string;
    parent_id: string;
    [key: string]: string;
}

function CategoryDialog({
    open,
    onOpenChange,
    category,
    categories,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    category: BlogCategory | null;
    categories: BlogOption[];
}) {
    const { r } = useBlogPanel();
    const editing = Boolean(category?.id);

    const form = useForm<CategoryFormValues>({
        name: category?.name ?? '',
        slug: category?.slug ?? '',
        description: category?.description ?? '',
        parent_id: category?.parent_id ? String(category.parent_id) : '',
    });

    const { data, setData, errors, processing } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.transform((values) => ({
            ...values,
            slug: values.slug === '' ? null : values.slug,
            description: values.description === '' ? null : values.description,
            parent_id: values.parent_id === '' ? null : values.parent_id,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        };

        if (editing && category?.id) {
            form.patch(r('categories.update', category.id), options);

            return;
        }

        form.post(r('categories.store'), options);
    }

    // Editing a category must not be able to select itself as its own parent.
    const parents = categories.filter((option) => option.value !== String(category?.id ?? ''));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit category' : 'New category'}</DialogTitle>
                        <DialogDescription>
                            The slug becomes part of the archive URL. Leave it blank to derive one from the name.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="category-name">
                            Name
                            <span className="text-destructive" aria-hidden="true">
                                *
                            </span>
                        </Label>
                        <Input
                            id="category-name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            aria-invalid={errors.name ? true : undefined}
                            required
                        />
                        {errors.name && <FieldError message={errors.name} />}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="category-slug">Slug</Label>
                        <Input
                            id="category-slug"
                            value={data.slug}
                            onChange={(event) => setData('slug', event.target.value)}
                            aria-invalid={errors.slug ? true : undefined}
                        />
                        {errors.slug && <FieldError message={errors.slug} />}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="category-parent">Parent</Label>
                        <Select
                            value={data.parent_id === '' ? NONE : data.parent_id}
                            onValueChange={(value) => setData('parent_id', value === NONE ? '' : value)}
                        >
                            <SelectTrigger id="category-parent" aria-invalid={errors.parent_id ? true : undefined}>
                                <SelectValue placeholder="Top level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Top level</SelectItem>
                                {parents.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.parent_id && <FieldError message={errors.parent_id} />}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="category-description">Description</Label>
                        <Textarea
                            id="category-description"
                            rows={3}
                            value={data.description}
                            onChange={(event) => setData('description', event.target.value)}
                        />
                        {errors.description && <FieldError message={errors.description} />}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editing ? 'Save category' : 'Create category'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function FieldError({ message }: { message: string }) {
    return (
        <p className="flex items-start gap-1.5 text-sm text-destructive">
            <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            {message}
        </p>
    );
}
