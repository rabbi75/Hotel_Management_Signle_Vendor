import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useDebouncedValue } from '@/components/app-shell/use-debounced-value';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    MediaAsset,
    MediaBreadcrumbEntry,
    MediaFilters,
    MediaFolder,
    MediaLimits,
    MediaPageMeta,
    MediaTypeOption,
    MediaUploaderOption,
} from '@/types/media';
import { router, useForm } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { ChevronLeft, ChevronRight, CircleAlert, FolderPlus, Images, LayoutGrid, List, Search, Trash2, Upload, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AssetThumbnail } from './asset-preview';
import { DetailDrawer } from './detail-drawer';
import { EditorDialog } from './editor-dialog';
import { FolderTree } from './folder-tree';
import { useMediaPanel } from './use-panel';
import { useMediaUploads } from './use-media-uploads';

const ALL = '__all__';

interface MediaIndexProps {
    assets: { data: MediaAsset[]; meta: MediaPageMeta };
    folders: MediaFolder[];
    current_folder: MediaFolder | null;
    breadcrumb: MediaBreadcrumbEntry[];
    filters: MediaFilters;
    types: MediaTypeOption[];
    uploaders: MediaUploaderOption[];
    limits: MediaLimits;
    can: { upload: boolean; manage_folders: boolean; update: boolean; delete: boolean };
}

/** Flattens the tree for the pickers that need a single list of paths. */
function flatten(folders: MediaFolder[]): MediaFolder[] {
    return folders.flatMap((folder) => [folder, ...flatten(folder.children)]);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PP') : '—';
}

export default function MediaIndex({
    assets,
    folders,
    current_folder: currentFolder,
    breadcrumb,
    filters,
    types,
    uploaders,
    limits,
    can,
}: MediaIndexProps) {
    const { Layout, home, r, may } = useMediaPanel();
    const confirm = useConfirm();

    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [search, setSearch] = useState(filters.search ?? '');
    const debouncedSearch = useDebouncedValue(search, 300);
    const [selected, setSelected] = useState<number[]>([]);
    const [detail, setDetail] = useState<MediaAsset | null>(null);
    const [editing, setEditing] = useState<MediaAsset | null>(null);
    const [dragging, setDragging] = useState(false);
    const [folderDialog, setFolderDialog] = useState<{ mode: 'create' | 'rename'; folder: MediaFolder | null } | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const dragDepth = useRef(0);

    const mayUpload = can.upload && may('media.upload');
    const mayUpdate = can.update && may('media.update');
    const mayDelete = can.delete && may('media.delete');
    const mayManageFolders = can.manage_folders && may('media.folders.manage');

    const flatFolders = useMemo(() => flatten(folders), [folders]);
    const uploads = useMediaUploads({
        folderId: currentFolder?.id ?? null,
        maxUploadKb: limits.max_upload_kb,
        allowedExtensions: limits.extensions,
    });

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl(home) ?? undefined }, { label: 'Media' }];

    // Any navigation is a fresh selection: acting on rows that are no longer on
    // screen is how a bulk delete surprises someone.
    useEffect(() => {
        setSelected([]);
    }, [assets.meta.current_page, currentFolder?.id, filters.search, filters.type, filters.uploader]);

    useEffect(() => {
        if ((filters.search ?? '') === debouncedSearch) {
            return;
        }

        navigate({ search: debouncedSearch || null, page: null });
        // `navigate` is stable for the life of this render pass; re-running on
        // its identity would loop.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedSearch]);

    function navigate(changes: Record<string, string | number | null>): void {
        const params = new URLSearchParams(window.location.search);

        for (const [key, value] of Object.entries(changes)) {
            if (value === null || value === '') {
                params.delete(key);
            } else {
                params.set(key, String(value));
            }
        }

        router.get(`${r('index')}${params.toString() ? `?${params}` : ''}`, undefined, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function toggleSelected(id: number, checked: boolean): void {
        setSelected((current) => (checked ? [...new Set([...current, id])] : current.filter((entry) => entry !== id)));
    }

    async function deleteAsset(asset: MediaAsset): Promise<void> {
        const ok = await confirm({
            title: `Delete ${asset.name}?`,
            description: 'Anything still pointing at this file will lose its image. Deleted files can be restored by an administrator.',
            variant: 'destructive',
            confirmLabel: 'Delete file',
        });

        if (ok) {
            router.delete(r('destroy', asset.id), {
                preserveScroll: true,
                onSuccess: () => setDetail(null),
            });
        }
    }

    async function bulkDelete(): Promise<void> {
        const ok = await confirm({
            title: `Delete ${selected.length} file(s)?`,
            description: 'Anything still pointing at these files will lose its image.',
            variant: 'destructive',
            confirmWord: 'delete',
            confirmLabel: 'Delete files',
        });

        if (ok) {
            router.post(
                r('bulk'),
                { action: 'delete', ids: selected },
                { preserveScroll: true, onSuccess: () => setSelected([]) },
            );
        }
    }

    function bulkMove(folderId: string): void {
        router.post(
            r('bulk'),
            { action: 'move', ids: selected, folder_id: folderId === ALL ? null : Number(folderId) },
            { preserveScroll: true, onSuccess: () => setSelected([]) },
        );
    }

    async function deleteFolder(folder: MediaFolder): Promise<void> {
        const ok = await confirm({
            title: `Delete the ${folder.name} folder?`,
            description: 'A folder can only be deleted once it is empty.',
            variant: 'destructive',
            confirmLabel: 'Delete folder',
        });

        if (ok) {
            router.delete(r('folders.destroy', folder.id), { preserveScroll: true });
        }
    }

    function onDrop(event: React.DragEvent<HTMLDivElement>): void {
        event.preventDefault();
        dragDepth.current = 0;
        setDragging(false);

        if (mayUpload) {
            uploads.enqueue(Array.from(event.dataTransfer.files));
        }
    }

    const meta = assets.meta;
    const isFiltered = Boolean(filters.search || filters.type || filters.uploader || filters.from || filters.to);

    return (
        <Layout title="Media" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Media"
                    description="Every file this workspace has uploaded."
                    actions={
                        <>
                            {mayManageFolders && (
                                <Button variant="outline" onClick={() => setFolderDialog({ mode: 'create', folder: null })}>
                                    <FolderPlus className="size-4" aria-hidden="true" />
                                    New folder
                                </Button>
                            )}
                            {mayUpload && (
                                <Button onClick={() => fileInput.current?.click()}>
                                    <Upload className="size-4" aria-hidden="true" />
                                    Upload
                                </Button>
                            )}
                        </>
                    }
                />

                <input
                    ref={fileInput}
                    type="file"
                    multiple
                    className="sr-only"
                    aria-label="Choose files to upload"
                    accept={limits.extensions.map((extension) => `.${extension}`).join(',')}
                    onChange={(event) => {
                        uploads.enqueue(Array.from(event.target.files ?? []));
                        event.target.value = '';
                    }}
                />

                <div className="grid gap-6 lg:grid-cols-[16rem_1fr]">
                    <aside className="min-w-0">
                        <Card>
                            <CardContent className="p-2">
                                <ScrollArea className="max-h-[60svh]">
                                    <FolderTree
                                        folders={folders}
                                        selectedId={currentFolder?.id ?? null}
                                        onSelect={(id) => navigate({ folder: id, page: null })}
                                        onRename={(folder) => setFolderDialog({ mode: 'rename', folder })}
                                        onDelete={(folder) => void deleteFolder(folder)}
                                        {...(mayUpload
                                            ? {
                                                  onDropFiles: (folder: MediaFolder | null, files: File[]) => {
                                                      if ((folder?.id ?? null) !== (currentFolder?.id ?? null)) {
                                                          navigate({ folder: folder?.id ?? null, page: null });
                                                      }

                                                      uploads.enqueue(files);
                                                  },
                                              }
                                            : {})}
                                        canManage={mayManageFolders}
                                        totalCount={meta.total}
                                    />
                                </ScrollArea>
                            </CardContent>
                        </Card>
                    </aside>

                    <div
                        className="min-w-0 space-y-4"
                        onDragEnter={(event) => {
                            event.preventDefault();
                            dragDepth.current += 1;
                            setDragging(true);
                        }}
                        onDragOver={(event) => event.preventDefault()}
                        onDragLeave={() => {
                            dragDepth.current = Math.max(0, dragDepth.current - 1);

                            if (dragDepth.current === 0) {
                                setDragging(false);
                            }
                        }}
                        onDrop={onDrop}
                    >
                        {breadcrumb.length > 0 && (
                            <nav aria-label="Folder path" className="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
                                <button
                                    type="button"
                                    onClick={() => navigate({ folder: null, page: null })}
                                    className="rounded-sm underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    All files
                                </button>
                                {breadcrumb.map((entry, index) => (
                                    <span key={entry.id} className="flex items-center gap-1">
                                        <ChevronRight className="size-3.5" aria-hidden="true" />
                                        <button
                                            type="button"
                                            onClick={() => navigate({ folder: entry.id, page: null })}
                                            aria-current={index === breadcrumb.length - 1 ? 'page' : undefined}
                                            className={cn(
                                                'rounded-sm underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring',
                                                index === breadcrumb.length - 1 && 'font-medium text-foreground',
                                            )}
                                        >
                                            {entry.name}
                                        </button>
                                    </span>
                                ))}
                            </nav>
                        )}

                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative min-w-0 flex-1 sm:max-w-xs">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search files…"
                                    aria-label="Search files"
                                    className="pl-9"
                                />
                            </div>

                            <Select
                                value={filters.type ?? ALL}
                                onValueChange={(value) => navigate({ type: value === ALL ? null : value, page: null })}
                            >
                                <SelectTrigger className="w-36" aria-label="Filter by type">
                                    <SelectValue placeholder="Any type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Any type</SelectItem>
                                    {types.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            {uploaders.length > 0 && (
                                <Select
                                    value={filters.uploader ?? ALL}
                                    onValueChange={(value) => navigate({ uploader: value === ALL ? null : value, page: null })}
                                >
                                    <SelectTrigger className="w-40" aria-label="Filter by uploader">
                                        <SelectValue placeholder="Anyone" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>Anyone</SelectItem>
                                        {uploaders.map((uploader) => (
                                            <SelectItem key={uploader.value} value={uploader.value}>
                                                {uploader.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}

                            <Input
                                type="date"
                                value={filters.from ?? ''}
                                onChange={(event) => navigate({ from: event.target.value || null, page: null })}
                                aria-label="Uploaded from"
                                className="w-40"
                            />
                            <Input
                                type="date"
                                value={filters.to ?? ''}
                                onChange={(event) => navigate({ to: event.target.value || null, page: null })}
                                aria-label="Uploaded until"
                                className="w-40"
                            />

                            <Select value={filters.sort} onValueChange={(value) => navigate({ sort: value, page: null })}>
                                <SelectTrigger className="w-36" aria-label="Sort by">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="created_at">Newest</SelectItem>
                                    <SelectItem value="name">Name</SelectItem>
                                    <SelectItem value="size">Size</SelectItem>
                                </SelectContent>
                            </Select>

                            <ToggleGroup
                                type="single"
                                value={view}
                                onValueChange={(value) => value && setView(value as 'grid' | 'list')}
                                variant="outline"
                                className="ml-auto"
                                aria-label="View mode"
                            >
                                <ToggleGroupItem value="grid" aria-label="Grid view">
                                    <LayoutGrid className="size-4" aria-hidden="true" />
                                </ToggleGroupItem>
                                <ToggleGroupItem value="list" aria-label="List view">
                                    <List className="size-4" aria-hidden="true" />
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>

                        {uploads.tasks.length > 0 && (
                            <Card>
                                <CardContent className="space-y-3 p-4">
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium">Uploads</p>
                                        <Button variant="ghost" size="sm" onClick={uploads.clearFinished} disabled={uploads.uploading}>
                                            Clear finished
                                        </Button>
                                    </div>
                                    <ul className="space-y-2" aria-live="polite">
                                        {uploads.tasks.map((task) => (
                                            <li key={task.id} className="space-y-1">
                                                <div className="flex items-center justify-between gap-3 text-sm">
                                                    <span className="min-w-0 flex-1 truncate">{task.name}</span>
                                                    <span
                                                        className={cn(
                                                            'shrink-0 text-xs tabular-nums',
                                                            task.status === 'error' ? 'text-destructive' : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {task.status === 'error'
                                                            ? task.error
                                                            : task.status === 'done'
                                                              ? 'Done'
                                                              : `${task.progress}%`}
                                                    </span>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => uploads.dismiss(task.id)}
                                                        aria-label={`Dismiss ${task.name}`}
                                                    >
                                                        <X className="size-3.5" aria-hidden="true" />
                                                    </Button>
                                                </div>
                                                {task.status !== 'error' && (
                                                    <Progress value={task.progress} aria-label={`${task.name} upload progress`} />
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        {selected.length > 0 && (
                            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-3">
                                <span className="text-sm font-medium">{selected.length} selected</span>

                                {mayUpdate && (
                                    <Select onValueChange={bulkMove}>
                                        <SelectTrigger className="w-56" aria-label="Move selection to a folder">
                                            <SelectValue placeholder="Move to folder…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ALL}>All files (no folder)</SelectItem>
                                            {flatFolders.map((folder) => (
                                                <SelectItem key={folder.id} value={String(folder.id)}>
                                                    {folder.path}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}

                                {mayDelete && (
                                    <Button variant="ghost" size="sm" onClick={() => void bulkDelete()}>
                                        <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                                        Delete
                                    </Button>
                                )}

                                <Button variant="ghost" size="sm" className="ml-auto" onClick={() => setSelected([])}>
                                    Clear selection
                                </Button>
                            </div>
                        )}

                        <div
                            className={cn(
                                'relative rounded-lg transition-colors',
                                dragging && 'ring-2 ring-ring ring-offset-2 ring-offset-background',
                            )}
                        >
                            {dragging && mayUpload && (
                                <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-background/85">
                                    <p className="flex items-center gap-2 text-sm font-medium">
                                        <Upload className="size-4" aria-hidden="true" />
                                        Drop to upload {currentFolder ? `into ${currentFolder.name}` : 'here'}
                                    </p>
                                </div>
                            )}

                            {assets.data.length === 0 ? (
                                <EmptyState
                                    icon={Images}
                                    title={isFiltered ? 'No files match these filters' : 'No files yet'}
                                    description={
                                        isFiltered
                                            ? 'Clear the filters to see the whole library.'
                                            : 'Drag files anywhere here, or use the upload button.'
                                    }
                                    action={
                                        mayUpload && !isFiltered ? (
                                            <Button size="sm" onClick={() => fileInput.current?.click()}>
                                                <Upload className="size-4" aria-hidden="true" />
                                                Upload files
                                            </Button>
                                        ) : null
                                    }
                                />
                            ) : view === 'grid' ? (
                                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                    {assets.data.map((asset) => {
                                        const checked = selected.includes(asset.id);

                                        return (
                                            <li key={asset.id}>
                                                <div
                                                    className={cn(
                                                        'group relative overflow-hidden rounded-lg border bg-card transition-colors',
                                                        checked
                                                            ? 'border-primary ring-1 ring-primary'
                                                            : 'border-border hover:border-primary/40',
                                                    )}
                                                >
                                                    <span className="absolute top-2 left-2 z-10">
                                                        <Checkbox
                                                            checked={checked}
                                                            onCheckedChange={(next) => toggleSelected(asset.id, next === true)}
                                                            aria-label={`Select ${asset.name}`}
                                                            className="bg-background"
                                                        />
                                                    </span>

                                                    <button
                                                        type="button"
                                                        onClick={() => setDetail(asset)}
                                                        className="block w-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                        aria-label={`Open details for ${asset.name}`}
                                                    >
                                                        <span className="flex aspect-square items-center justify-center bg-muted p-2">
                                                            <AssetThumbnail asset={asset} />
                                                        </span>
                                                        <span className="block space-y-1 p-2 text-left">
                                                            <span className="block truncate text-sm font-medium">{asset.name}</span>
                                                            <span className="block truncate text-xs text-muted-foreground">
                                                                {asset.size_human}
                                                            </span>
                                                        </span>
                                                    </button>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : (
                                <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                                    {assets.data.map((asset) => {
                                        const checked = selected.includes(asset.id);

                                        return (
                                            <li key={asset.id} className="flex items-center gap-3 p-3">
                                                <Checkbox
                                                    checked={checked}
                                                    onCheckedChange={(next) => toggleSelected(asset.id, next === true)}
                                                    aria-label={`Select ${asset.name}`}
                                                />
                                                <span className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded bg-muted">
                                                    <AssetThumbnail asset={asset} />
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => setDetail(asset)}
                                                    className="min-w-0 flex-1 rounded-sm text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                >
                                                    <span className="block truncate text-sm font-medium">{asset.name}</span>
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {asset.folder ?? 'All files'} · {formatDate(asset.created_at)}
                                                    </span>
                                                </button>
                                                <Badge variant="outline" className="hidden sm:inline-flex">
                                                    {asset.type_label}
                                                </Badge>
                                                <span className="hidden w-20 text-right text-xs text-muted-foreground tabular-nums sm:block">
                                                    {asset.size_human}
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>

                        {meta.last_page > 1 && (
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm text-muted-foreground" role="status" aria-live="polite">
                                    {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
                                </p>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page <= 1}
                                        onClick={() => navigate({ page: meta.current_page - 1 })}
                                    >
                                        <ChevronLeft className="size-4" aria-hidden="true" />
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page >= meta.last_page}
                                        onClick={() => navigate({ page: meta.current_page + 1 })}
                                    >
                                        Next
                                        <ChevronRight className="size-4" aria-hidden="true" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <DetailDrawer
                asset={detail}
                folders={flatFolders}
                open={detail !== null}
                onOpenChange={(next) => !next && setDetail(null)}
                onDelete={(asset) => void deleteAsset(asset)}
                onEdit={(asset) => {
                    setDetail(null);
                    setEditing(asset);
                }}
                canUpdate={mayUpdate}
                canDelete={mayDelete}
            />

            <EditorDialog asset={editing} open={editing !== null} onOpenChange={(next) => !next && setEditing(null)} />

            <FolderDialog
                state={folderDialog}
                folders={flatFolders}
                parentId={currentFolder?.id ?? null}
                onClose={() => setFolderDialog(null)}
            />
        </Layout>
    );
}

interface FolderDialogProps {
    state: { mode: 'create' | 'rename'; folder: MediaFolder | null } | null;
    folders: MediaFolder[];
    parentId: number | null;
    onClose: () => void;
}

function FolderDialog({ state, folders, parentId, onClose }: FolderDialogProps) {
    const { r } = useMediaPanel();
    const form = useForm<{ name: string; parent_id: string; [key: string]: string }>({
        name: '',
        parent_id: '',
    });

    const { setData, clearErrors } = form;

    useEffect(() => {
        clearErrors();
        setData({
            name: state?.folder?.name ?? '',
            parent_id: state?.folder ? String(state.folder.parent_id ?? '') : String(parentId ?? ''),
        });
    }, [clearErrors, parentId, setData, state]);

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onClose };

        if (state?.mode === 'rename' && state.folder) {
            form.transform((values) => ({ name: values.name }));
            form.put(r('folders.update', state.folder.id), options);

            return;
        }

        form.transform((values) => ({
            name: values.name,
            parent_id: values.parent_id === '' ? null : values.parent_id,
        }));
        form.post(r('folders.store'), options);
    }

    return (
        <Dialog open={state !== null} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-md">
                <form noValidate onSubmit={submit} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>{state?.mode === 'rename' ? `Rename ${state.folder?.name}` : 'New folder'}</DialogTitle>
                        <DialogDescription>
                            {state?.mode === 'rename'
                                ? 'Renaming a folder updates the path of everything beneath it.'
                                : 'Folders group files; they do not restrict who can see them.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="folder-name">
                            Name
                            <span className="text-destructive" aria-hidden="true">
                                *
                            </span>
                        </Label>
                        <Input
                            id="folder-name"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            required
                            aria-invalid={form.errors.name ? true : undefined}
                            aria-describedby={form.errors.name ? 'folder-name-error' : undefined}
                        />
                        {form.errors.name && (
                            <p id="folder-name-error" className="flex items-start gap-1.5 text-sm text-destructive">
                                <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                {form.errors.name}
                            </p>
                        )}
                    </div>

                    {state?.mode === 'create' && folders.length > 0 && (
                        <div className="space-y-2">
                            <Label htmlFor="folder-parent">Parent folder</Label>
                            <Select
                                value={form.data.parent_id === '' ? ALL : form.data.parent_id}
                                onValueChange={(value) => form.setData('parent_id', value === ALL ? '' : value)}
                            >
                                <SelectTrigger id="folder-parent">
                                    <SelectValue placeholder="Top level" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Top level</SelectItem>
                                    {folders.map((folder) => (
                                        <SelectItem key={folder.id} value={String(folder.id)}>
                                            {folder.path}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {form.errors.parent_id && (
                        <p className="flex items-start gap-1.5 text-sm text-destructive">
                            <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                            {form.errors.parent_id}
                        </p>
                    )}

                    <Separator />

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            {state?.mode === 'rename' ? 'Rename folder' : 'Create folder'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
