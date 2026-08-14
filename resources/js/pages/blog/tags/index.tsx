import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { BlogTag } from '@/types/blog';
import { router, useForm } from '@inertiajs/react';
import { CircleAlert, Pencil, Plus, SearchX, Tag as TagIcon, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useBlogPanel } from '../use-panel';

interface TagsIndexProps {
    table: TablePayload<BlogTag>;
    can: { manage: boolean };
}

export default function TagsIndex({ table, can }: TagsIndexProps) {
    const { Layout, home, r, may } = useBlogPanel();
    const confirm = useConfirm();

    const [editing, setEditing] = useState<BlogTag | null>(null);
    const [open, setOpen] = useState(false);

    const mayManage = can.manage && may('blog.taxonomy.manage');
    const filtered = Boolean(table.state.search);

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl(home) ?? undefined }, { label: 'Tags' }];

    async function remove(tag: BlogTag): Promise<void> {
        const ok = await confirm({
            title: `Delete the “${tag.name}” tag?`,
            description:
                (tag.posts_count ?? 0) > 0
                    ? `It is removed from ${tag.posts_count} posts. The posts themselves are untouched.`
                    : 'No posts use this tag.',
            variant: 'destructive',
            confirmLabel: 'Delete tag',
        });

        if (ok) {
            router.delete(r('tags.destroy', tag.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<BlogTag> = {
        name: (row) => <span className="block truncate text-sm font-medium">{row.name}</span>,
        slug: (row) => <TextCell value={`/${row.slug}`} muted />,
        posts_count: (row) => <span className="tabular-nums">{row.posts_count ?? 0}</span>,
        created_at: (row) => <DateCell value={row.created_at} />,
    };

    function rowActions(row: BlogTag): RowAction[] {
        return [
            {
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => {
                    setEditing(row);
                    setOpen(true);
                },
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
        <Layout title="Tags" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Tags"
                    description="Cross-cutting labels. A post has one category but any number of tags."
                    actions={
                        mayManage ? (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setOpen(true);
                                }}
                            >
                                <Plus className="size-4" aria-hidden="true" />
                                New tag
                            </Button>
                        ) : null
                    }
                />

                <DataTable<BlogTag>
                    payload={table}
                    propKey="table"
                    name="tags"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={mayManage ? rowActions : undefined}
                    searchPlaceholder="Search tags…"
                    caption="Blog tags in this workspace"
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : TagIcon}
                            title={filtered ? 'No tags match your search' : 'No tags yet'}
                            description={filtered ? 'Clear the search to see them all.' : 'Add a few and start labelling posts.'}
                            className="border-0"
                        />
                    }
                />
            </div>

            <TagDialog open={open} onOpenChange={setOpen} tag={editing} key={editing?.id ?? 'new'} />
        </Layout>
    );
}

interface TagFormValues {
    name: string;
    slug: string;
    [key: string]: string;
}

function TagDialog({ open, onOpenChange, tag }: { open: boolean; onOpenChange: (open: boolean) => void; tag: BlogTag | null }) {
    const { r } = useBlogPanel();
    const editing = tag !== null;

    const form = useForm<TagFormValues>({
        name: tag?.name ?? '',
        slug: tag?.slug ?? '',
    });

    const { data, setData, errors, processing } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.transform((values) => ({ ...values, slug: values.slug === '' ? null : values.slug }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        };

        if (editing && tag) {
            form.patch(r('tags.update', tag.id), options);

            return;
        }

        form.post(r('tags.store'), options);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit tag' : 'New tag'}</DialogTitle>
                        <DialogDescription>Leave the slug blank to derive one from the name.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="tag-name">
                            Name
                            <span className="text-destructive" aria-hidden="true">
                                *
                            </span>
                        </Label>
                        <Input
                            id="tag-name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            aria-invalid={errors.name ? true : undefined}
                            required
                        />
                        {errors.name && (
                            <p className="flex items-start gap-1.5 text-sm text-destructive">
                                <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="tag-slug">Slug</Label>
                        <Input
                            id="tag-slug"
                            value={data.slug}
                            onChange={(event) => setData('slug', event.target.value)}
                            aria-invalid={errors.slug ? true : undefined}
                        />
                        {errors.slug && (
                            <p className="flex items-start gap-1.5 text-sm text-destructive">
                                <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                {errors.slug}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editing ? 'Save tag' : 'Create tag'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
