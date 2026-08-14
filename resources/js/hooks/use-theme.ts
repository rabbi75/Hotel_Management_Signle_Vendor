import type { Appearance } from '@/types';
import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'appearance';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

function prefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolve(appearance: Appearance): boolean {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
}

function apply(appearance: Appearance): void {
    document.documentElement.classList.toggle('dark', resolve(appearance));
}

function persist(appearance: Appearance): void {
    localStorage.setItem(STORAGE_KEY, appearance);

    // Mirrored into a cookie so the server can stamp the `dark` class onto the
    // initial HTML; localStorage alone would still flash on first paint.
    document.cookie = `${STORAGE_KEY}=${appearance};path=/;max-age=${COOKIE_MAX_AGE};SameSite=Lax`;
}

function stored(): Appearance {
    return (localStorage.getItem(STORAGE_KEY) as Appearance | null) ?? 'system';
}

/**
 * Applies the persisted theme and keeps `system` in sync with the OS setting.
 * Called once from the application entrypoint.
 */
export function initialiseTheme(): void {
    apply(stored());

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (stored() === 'system') {
            apply('system');
        }
    });
}

export function useTheme() {
    const [appearance, setAppearanceState] = useState<Appearance>('system');

    useEffect(() => {
        setAppearanceState(stored());
    }, []);

    const setAppearance = useCallback((next: Appearance) => {
        setAppearanceState(next);
        persist(next);
        apply(next);
    }, []);

    return {
        appearance,
        setAppearance,
        isDark: typeof document !== 'undefined' && document.documentElement.classList.contains('dark'),
    } as const;
}
