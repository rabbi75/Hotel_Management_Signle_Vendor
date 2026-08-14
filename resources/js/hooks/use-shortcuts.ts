import { useEffect } from 'react';

export interface Shortcut {
    /** Lower-case `event.key`, e.g. 'k', '/', '?'. */
    key: string;
    meta?: boolean;
    shift?: boolean;
    alt?: boolean;
    label: string;
    group: string;
    handler: (event: KeyboardEvent) => void;
    /** Fire even while focus is inside a text field. Off by default. */
    allowInInput?: boolean;
}

function isTypingTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable;
}

/**
 * Binds a set of keyboard shortcuts for as long as the component is mounted.
 *
 * `meta` matches Cmd on macOS and Ctrl elsewhere, which is what users of either
 * platform expect from the same shortcut.
 */
export function useShortcuts(shortcuts: Shortcut[]): void {
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent): void {
            for (const shortcut of shortcuts) {
                if (event.key.toLowerCase() !== shortcut.key.toLowerCase()) {
                    continue;
                }

                const metaMatches = shortcut.meta ? event.metaKey || event.ctrlKey : !event.metaKey && !event.ctrlKey;

                if (!metaMatches) {
                    continue;
                }

                if (Boolean(shortcut.shift) !== event.shiftKey) {
                    continue;
                }

                if (Boolean(shortcut.alt) !== event.altKey) {
                    continue;
                }

                if (!shortcut.allowInInput && isTypingTarget(event.target)) {
                    continue;
                }

                event.preventDefault();
                shortcut.handler(event);

                return;
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [shortcuts]);
}
