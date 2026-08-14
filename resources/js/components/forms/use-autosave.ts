import { useCallback, useEffect, useRef, useState } from 'react';

export type AutosaveStatus = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

export interface UseAutosaveOptions<TValues> {
    values: TValues;
    /** Resolves when the write has landed; reject to surface an error. */
    save: (values: TValues) => Promise<void> | void;
    delay?: number;
    enabled?: boolean;
    /** Compare two snapshots. Defaults to a JSON comparison. */
    isEqual?: (a: TValues, b: TValues) => boolean;
}

export interface UseAutosaveResult {
    status: AutosaveStatus;
    /** Human-readable line for the status region. */
    message: string;
    lastSavedAt: Date | null;
    error: string | null;
    /** Writes immediately, cancelling any pending debounce. */
    saveNow: () => Promise<void>;
}

const MESSAGES: Record<AutosaveStatus, string> = {
    idle: 'All changes saved',
    dirty: 'Unsaved changes',
    saving: 'Saving…',
    saved: 'Saved',
    error: 'Could not save',
};

function jsonEqual<TValues>(a: TValues, b: TValues): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

/**
 * Debounced autosave with an explicit status line.
 *
 * The baseline is the last *successfully persisted* snapshot, so a failed save
 * leaves the form dirty and the next edit retries rather than silently
 * dropping the change.
 */
export function useAutosave<TValues>({
    values,
    save,
    delay = 1200,
    enabled = true,
    isEqual = jsonEqual,
}: UseAutosaveOptions<TValues>): UseAutosaveResult {
    const [status, setStatus] = useState<AutosaveStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);

    const baseline = useRef(values);
    const latest = useRef(values);
    latest.current = values;

    const saveRef = useRef(save);
    saveRef.current = save;

    const timer = useRef<number | null>(null);

    const flush = useCallback(async () => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }

        const snapshot = latest.current;

        if (isEqual(snapshot, baseline.current)) {
            return;
        }

        setStatus('saving');
        setError(null);

        try {
            await saveRef.current(snapshot);
            baseline.current = snapshot;
            setLastSavedAt(new Date());
            setStatus('saved');
        } catch (cause) {
            setStatus('error');
            setError(cause instanceof Error ? cause.message : 'Save failed.');
        }
    }, [isEqual]);

    useEffect(() => {
        if (!enabled || isEqual(values, baseline.current)) {
            return;
        }

        setStatus('dirty');

        timer.current = window.setTimeout(() => {
            timer.current = null;
            void flush();
        }, delay);

        return () => {
            if (timer.current !== null) {
                window.clearTimeout(timer.current);
                timer.current = null;
            }
        };
    }, [values, enabled, delay, flush, isEqual]);

    return {
        status,
        message: status === 'error' && error ? error : MESSAGES[status],
        lastSavedAt,
        error,
        saveNow: flush,
    };
}
