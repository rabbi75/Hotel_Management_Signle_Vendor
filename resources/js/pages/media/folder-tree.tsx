import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { MediaFolder } from '@/types/media';
import { ChevronDown, ChevronRight, Folder, FolderOpen, Images, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

export interface FolderTreeProps {
    folders: MediaFolder[];
    selectedId: number | null;
    onSelect: (id: number | null) => void;
    onRename: (folder: MediaFolder) => void;
    onDelete: (folder: MediaFolder) => void;
    /** Drops files onto a folder to upload straight into it. */
    onDropFiles?: (folder: MediaFolder | null, files: File[]) => void;
    canManage: boolean;
    totalCount: number;
}

export function FolderTree({ folders, selectedId, onSelect, onRename, onDelete, onDropFiles, canManage, totalCount }: FolderTreeProps) {
    return (
        <nav aria-label="Folders">
            <ul role="tree" aria-label="Media folders" className="space-y-0.5">
                <li role="none">
                    <FolderRow
                        label="All files"
                        count={totalCount}
                        icon={Images}
                        depth={0}
                        selected={selectedId === null}
                        onSelect={() => onSelect(null)}
                        {...(onDropFiles ? { onDropFiles: (files: File[]) => onDropFiles(null, files) } : {})}
                    />
                </li>

                {folders.map((folder) => (
                    <FolderBranch
                        key={folder.id}
                        folder={folder}
                        depth={0}
                        selectedId={selectedId}
                        onSelect={onSelect}
                        onRename={onRename}
                        onDelete={onDelete}
                        {...(onDropFiles ? { onDropFiles } : {})}
                        canManage={canManage}
                    />
                ))}
            </ul>
        </nav>
    );
}

interface BranchProps {
    folder: MediaFolder;
    depth: number;
    selectedId: number | null;
    onSelect: (id: number | null) => void;
    onRename: (folder: MediaFolder) => void;
    onDelete: (folder: MediaFolder) => void;
    onDropFiles?: (folder: MediaFolder | null, files: File[]) => void;
    canManage: boolean;
}

function FolderBranch({ folder, depth, selectedId, onSelect, onRename, onDelete, onDropFiles, canManage }: BranchProps) {
    const [expanded, setExpanded] = useState(depth === 0);
    const hasChildren = folder.children.length > 0;
    const selected = selectedId === folder.id;

    return (
        <li role="treeitem" aria-expanded={hasChildren ? expanded : undefined} aria-selected={selected} className="min-w-0">
            <FolderRow
                label={folder.name}
                count={folder.assets_count}
                icon={selected ? FolderOpen : Folder}
                depth={depth}
                selected={selected}
                onSelect={() => onSelect(folder.id)}
                {...(hasChildren ? { expanded, onToggle: () => setExpanded((value) => !value) } : {})}
                {...(onDropFiles ? { onDropFiles: (files: File[]) => onDropFiles(folder, files) } : {})}
                actions={
                    canManage ? (
                        <>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onRename(folder);
                                }}
                                aria-label={`Rename ${folder.name}`}
                            >
                                <Pencil className="size-3.5" aria-hidden="true" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onDelete(folder);
                                }}
                                aria-label={`Delete ${folder.name}`}
                            >
                                <Trash2 className="size-3.5 text-destructive" aria-hidden="true" />
                            </Button>
                        </>
                    ) : null
                }
            />

            {hasChildren && expanded && (
                <ul role="group" className="space-y-0.5">
                    {folder.children.map((child) => (
                        <FolderBranch
                            key={child.id}
                            folder={child}
                            depth={depth + 1}
                            selectedId={selectedId}
                            onSelect={onSelect}
                            onRename={onRename}
                            onDelete={onDelete}
                            {...(onDropFiles ? { onDropFiles } : {})}
                            canManage={canManage}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

interface RowProps {
    label: string;
    count: number | null;
    icon: typeof Folder;
    depth: number;
    selected: boolean;
    onSelect: () => void;
    expanded?: boolean;
    onToggle?: () => void;
    onDropFiles?: (files: File[]) => void;
    actions?: React.ReactNode;
}

function FolderRow({ label, count, icon: Icon, depth, selected, onSelect, expanded, onToggle, onDropFiles, actions }: RowProps) {
    const [over, setOver] = useState(false);

    return (
        <div
            className={cn(
                'group flex items-center gap-1 rounded-md pr-1 transition-colors',
                selected ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'hover:bg-muted/60',
                over && 'ring-2 ring-ring',
            )}
            style={{ paddingInlineStart: `${depth * 0.75}rem` }}
            onDragOver={
                onDropFiles
                    ? (event) => {
                          event.preventDefault();
                          setOver(true);
                      }
                    : undefined
            }
            onDragLeave={onDropFiles ? () => setOver(false) : undefined}
            onDrop={
                onDropFiles
                    ? (event) => {
                          event.preventDefault();
                          setOver(false);
                          onDropFiles(Array.from(event.dataTransfer.files));
                      }
                    : undefined
            }
        >
            {onToggle ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    className="size-6"
                    onClick={onToggle}
                    aria-label={expanded ? `Collapse ${label}` : `Expand ${label}`}
                >
                    {expanded ? (
                        <ChevronDown className="size-3.5" aria-hidden="true" />
                    ) : (
                        <ChevronRight className="size-3.5" aria-hidden="true" />
                    )}
                </Button>
            ) : (
                <span className="size-6 shrink-0" aria-hidden="true" />
            )}

            <button
                type="button"
                onClick={onSelect}
                aria-current={selected ? 'true' : undefined}
                className="flex min-w-0 flex-1 items-center gap-2 rounded-sm py-1.5 text-left text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                <span className="min-w-0 flex-1 truncate">{label}</span>
                {count !== null && count > 0 && <span className="shrink-0 text-xs text-muted-foreground tabular-nums">{count}</span>}
            </button>

            {actions && (
                <span className="flex shrink-0 items-center opacity-0 group-focus-within:opacity-100 group-hover:opacity-100">
                    {actions}
                </span>
            )}
        </div>
    );
}
