import type { MediaUploadTask } from '@/types/media';
import { router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import { useMediaPanel } from './use-panel';

export interface UseMediaUploadsOptions {
    folderId: number | null;
    maxUploadKb: number;
    allowedExtensions: string[];
}

export interface UseMediaUploads {
    tasks: MediaUploadTask[];
    uploading: boolean;
    /** Queues files and uploads them one at a time. */
    enqueue: (files: File[]) => void;
    dismiss: (id: string) => void;
    clearFinished: () => void;
}

let sequence = 0;

function extensionOf(name: string): string {
    return name.slice(name.lastIndexOf('.') + 1).toLowerCase();
}

/**
 * Per-file upload with progress.
 *
 * Files are posted one at a time through axios rather than as one Inertia
 * visit, because a single request can only report one progress number — and a
 * fifty-file drop needs a bar per file, and needs a failure to be a failure of
 * that file rather than of the whole batch. The grid is refreshed once at the
 * end, not once per file.
 */
export function useMediaUploads({ folderId, maxUploadKb, allowedExtensions }: UseMediaUploadsOptions): UseMediaUploads {
    const { r } = useMediaPanel();
    const [tasks, setTasks] = useState<MediaUploadTask[]>([]);
    const running = useRef(false);

    const patch = useCallback((id: string, changes: Partial<MediaUploadTask>): void => {
        setTasks((current) => current.map((task) => (task.id === id ? { ...task, ...changes } : task)));
    }, []);

    const enqueue = useCallback(
        (files: File[]): void => {
            if (files.length === 0) {
                return;
            }

            const queued = files.map((file) => {
                sequence += 1;

                const tooLarge = file.size > maxUploadKb * 1024;
                const wrongType = !allowedExtensions.includes(extensionOf(file.name));

                const task: MediaUploadTask & { file: File } = {
                    id: `upload-${sequence}`,
                    name: file.name,
                    size: file.size,
                    progress: 0,
                    status: tooLarge || wrongType ? 'error' : 'queued',
                    error: tooLarge
                        ? `Larger than the ${Math.round(maxUploadKb / 1024)} MB limit`
                        : wrongType
                          ? 'This file type is not allowed'
                          : null,
                    file,
                };

                return task;
            });

            setTasks((current) => [...current, ...queued.map(({ file: _file, ...task }) => task)]);

            const sendable = queued.filter((task) => task.status === 'queued');

            if (sendable.length === 0) {
                return;
            }

            void (async () => {
                if (running.current) {
                    return;
                }

                running.current = true;

                let anySucceeded = false;

                for (const task of sendable) {
                    patch(task.id, { status: 'uploading', progress: 0 });

                    const body = new FormData();
                    body.append('files[]', task.file);

                    if (folderId !== null) {
                        body.append('folder_id', String(folderId));
                    }

                    try {
                        await window.axios.post(r('upload'), body, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            onUploadProgress: (event) => {
                                const total = event.total ?? task.size;
                                patch(task.id, { progress: total > 0 ? Math.round((event.loaded / total) * 100) : 0 });
                            },
                        });

                        anySucceeded = true;
                        patch(task.id, { status: 'done', progress: 100, error: null });
                    } catch {
                        patch(task.id, { status: 'error', error: 'Upload failed. Try again.' });
                    }
                }

                running.current = false;

                if (anySucceeded) {
                    router.reload({ only: ['assets', 'folders'] });
                }
            })();
        },
        [allowedExtensions, folderId, maxUploadKb, patch, r],
    );

    const dismiss = useCallback((id: string): void => {
        setTasks((current) => current.filter((task) => task.id !== id));
    }, []);

    const clearFinished = useCallback((): void => {
        setTasks((current) => current.filter((task) => task.status !== 'done'));
    }, []);

    return {
        tasks,
        uploading: tasks.some((task) => task.status === 'uploading' || task.status === 'queued'),
        enqueue,
        dismiss,
        clearFinished,
    };
}
