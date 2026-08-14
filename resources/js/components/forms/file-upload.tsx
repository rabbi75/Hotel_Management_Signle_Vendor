import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { FileText, ImageIcon, Paperclip, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';

export interface UploadedFileState {
    file: File;
    progress: number;
    error: string | null;
}

export interface FileUploadProps {
    value: File[];
    onChange: (files: File[]) => void;
    multiple?: boolean;
    accept?: string;
    maxSizeMb?: number;
    maxFiles?: number;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string | undefined;
    /** Optional real upload; report progress 0–100 and resolve when stored. */
    upload?: (file: File, onProgress: (percent: number) => void) => Promise<void>;
    className?: string;
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value.toFixed(1)} ${units[unit]}`;
}

function accepts(file: File, accept: string | undefined): boolean {
    if (!accept) {
        return true;
    }

    return accept
        .split(',')
        .map((entry) => entry.trim().toLowerCase())
        .some((entry) => {
            if (entry.endsWith('/*')) {
                return file.type.toLowerCase().startsWith(entry.slice(0, -1));
            }

            if (entry.startsWith('.')) {
                return file.name.toLowerCase().endsWith(entry);
            }

            return file.type.toLowerCase() === entry;
        });
}

export function FileUpload({
    value,
    onChange,
    multiple = false,
    accept,
    maxSizeMb,
    maxFiles,
    disabled = false,
    invalid = false,
    describedBy,
    upload,
    className,
}: FileUploadProps) {
    const inputId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [states, setStates] = useState<Record<string, { progress: number; error: string | null }>>({});

    const previews = useMemo(
        () => value.map((file) => ({ file, url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null })),
        [value],
    );

    // Object URLs are per-render; revoking on cleanup keeps the blob store flat.
    useEffect(
        () => () => {
            for (const preview of previews) {
                if (preview.url) {
                    URL.revokeObjectURL(preview.url);
                }
            }
        },
        [previews],
    );

    const key = useCallback((file: File) => `${file.name}:${file.size}:${file.lastModified}`, []);

    const add = useCallback(
        (incoming: FileList | File[]) => {
            const candidates = [...incoming];
            const accepted: File[] = [];
            const rejected: Record<string, { progress: number; error: string | null }> = {};

            for (const file of candidates) {
                if (!accepts(file, accept)) {
                    rejected[key(file)] = { progress: 0, error: 'Unsupported file type.' };

                    continue;
                }

                if (maxSizeMb !== undefined && file.size > maxSizeMb * 1024 * 1024) {
                    rejected[key(file)] = { progress: 0, error: `Larger than ${maxSizeMb} MB.` };

                    continue;
                }

                accepted.push(file);
            }

            const merged = multiple ? [...value, ...accepted] : accepted.slice(0, 1);
            const limited = maxFiles ? merged.slice(0, maxFiles) : merged;

            setStates((current) => ({ ...current, ...rejected }));
            onChange(limited);

            if (!upload) {
                return;
            }

            for (const file of accepted) {
                const id = key(file);
                setStates((current) => ({ ...current, [id]: { progress: 0, error: null } }));

                void upload(file, (percent) => setStates((current) => ({ ...current, [id]: { progress: percent, error: null } })))
                    .then(() => setStates((current) => ({ ...current, [id]: { progress: 100, error: null } })))
                    .catch((error: unknown) =>
                        setStates((current) => ({
                            ...current,
                            [id]: { progress: 0, error: error instanceof Error ? error.message : 'Upload failed.' },
                        })),
                    );
            }
        },
        [accept, key, maxFiles, maxSizeMb, multiple, onChange, upload, value],
    );

    function remove(file: File): void {
        onChange(value.filter((entry) => key(entry) !== key(file)));
        setStates((current) => {
            const next = { ...current };
            delete next[key(file)];

            return next;
        });
    }

    const rejectedEntries = Object.entries(states).filter(([id, state]) => state.error && !value.some((file) => key(file) === id));

    return (
        <div className={cn('space-y-3', className)}>
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    if (!disabled) {
                        setDragging(true);
                    }
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);

                    if (!disabled && event.dataTransfer.files.length > 0) {
                        add(event.dataTransfer.files);
                    }
                }}
                className={cn(
                    'flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border px-6 py-8 text-center transition-colors',
                    dragging && 'border-primary bg-primary/5',
                    invalid && 'border-destructive',
                    disabled && 'opacity-60',
                )}
            >
                <Upload className="size-6 text-muted-foreground" aria-hidden="true" />
                <p className="text-sm">
                    <label
                        htmlFor={inputId}
                        className="cursor-pointer font-medium text-primary underline-offset-4 focus-within:underline hover:underline"
                    >
                        Choose {multiple ? 'files' : 'a file'}
                    </label>{' '}
                    or drag and drop
                </p>
                <p className="text-xs text-muted-foreground">
                    {accept ? `${accept} · ` : ''}
                    {maxSizeMb ? `up to ${maxSizeMb} MB` : 'any size'}
                </p>

                <input
                    ref={inputRef}
                    id={inputId}
                    type="file"
                    className="sr-only"
                    multiple={multiple}
                    accept={accept}
                    disabled={disabled}
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                    onChange={(event) => {
                        if (event.target.files) {
                            add(event.target.files);
                        }

                        // Reset so re-picking the same file still fires a change.
                        event.target.value = '';
                    }}
                />
            </div>

            {(previews.length > 0 || rejectedEntries.length > 0) && (
                <ul className="space-y-2">
                    {previews.map(({ file, url }) => {
                        const state = states[key(file)];

                        return (
                            <li key={key(file)} className="flex items-center gap-3 rounded-md border border-border p-2">
                                <span className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted">
                                    {url ? (
                                        <img src={url} alt="" className="size-full object-cover" />
                                    ) : file.type.startsWith('image/') ? (
                                        <ImageIcon className="size-4 text-muted-foreground" aria-hidden="true" />
                                    ) : (
                                        <FileText className="size-4 text-muted-foreground" aria-hidden="true" />
                                    )}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{file.name}</p>
                                    <p className="text-xs text-muted-foreground">{formatBytes(file.size)}</p>
                                    {state && state.progress > 0 && state.progress < 100 && (
                                        <Progress value={state.progress} className="mt-1 h-1" aria-label={`Uploading ${file.name}`} />
                                    )}
                                    {state?.error && <p className="text-xs font-medium text-destructive">{state.error}</p>}
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() => remove(file)}
                                    aria-label={`Remove ${file.name}`}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                </Button>
                            </li>
                        );
                    })}

                    {rejectedEntries.map(([id, state]) => (
                        <li key={id} className="flex items-center gap-3 rounded-md border border-destructive/40 bg-destructive/5 p-2">
                            <Paperclip className="size-4 shrink-0 text-destructive" aria-hidden="true" />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm">{id.split(':')[0]}</p>
                                <p className="text-xs font-medium text-destructive">{state.error}</p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Dismiss"
                                onClick={() =>
                                    setStates((current) => {
                                        const next = { ...current };
                                        delete next[id];

                                        return next;
                                    })
                                }
                            >
                                <X className="size-4" aria-hidden="true" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
