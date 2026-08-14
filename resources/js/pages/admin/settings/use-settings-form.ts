import type { Method, RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

export type SettingsPayload = Record<string, unknown>;

export interface UseSettingsSubmitResult {
    /** Accepts any panel shape; the values are scalars, booleans or string lists. */
    submit: (values: object, onSuccess?: () => void) => void;
    submitting: boolean;
    saved: boolean;
}

export interface UseSettingsSubmitOptions {
    method?: Method;
    /**
     * Send the panel as multipart instead of JSON. Needed by any panel carrying
     * a `File`, which `router.visit` cannot serialise — the request becomes a
     * POST with a spoofed `_method`, because browsers cannot send a multipart
     * body on a PUT.
     */
    multipart?: boolean;
}

/**
 * Posts a settings panel and tracks just enough state for `FormActions` to show
 * "Saving…" and then "Saved". Validation errors are not tracked here: Inertia
 * puts them on the page's `errors` bag, which the form reads directly.
 */
export function useSettingsSubmit(url: string, options: Method | UseSettingsSubmitOptions = 'put'): UseSettingsSubmitResult {
    const { method = 'put', multipart = false } = typeof options === 'string' ? { method: options } : options;

    const [submitting, setSubmitting] = useState(false);
    const [saved, setSaved] = useState(false);

    const submit = useCallback(
        (values: object, onSuccess?: () => void) => {
            setSubmitting(true);
            setSaved(false);

            const data = multipart ? { ...values, _method: method } : values;

            router.visit(url, {
                method: multipart ? 'post' : method,
                forceFormData: multipart,
                // Every value here is a scalar, a boolean, a string list or — in a
                // multipart panel — a File, all of which Inertia serialises; the
                // cast is only needed because the panels are typed by their own
                // shapes rather than by the wire type.
                data: data as RequestPayload,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setSaved(true);
                    onSuccess?.();
                },
                onFinish: () => setSubmitting(false),
            });
        },
        [url, method, multipart],
    );

    return { submit, submitting, saved };
}

/**
 * A blank secret input means "leave the stored credential alone", never "clear
 * it" — the server drops `null` secrets from the write set.
 */
export function untouchedSecret(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value : null;
}

/** Splits a textarea of one-entry-per-line into the array the server expects. */
export function linesToList(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.filter((entry): entry is string => typeof entry === 'string');
    }

    if (typeof value !== 'string') {
        return [];
    }

    return value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
}

export function listToLines(value: string[] | null | undefined): string {
    return (value ?? []).join('\n');
}
