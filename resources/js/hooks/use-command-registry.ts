import { createScope, registerCommands, registerShortcutHints } from '@/components/command-palette/command-registry';
import { useCommandStore, type CommandAction, type ShortcutHint } from '@/stores/command-store';
import { useEffect, useRef } from 'react';

function commandSignature(commands: CommandAction[]): string {
    return commands.map((command) => `${command.id}|${command.group ?? ''}|${command.label}|${command.subtitle ?? ''}`).join('~');
}

function hintSignature(hints: ShortcutHint[]): string {
    return hints.map((hint) => `${hint.group}|${hint.label}|${hint.keys.join('+')}`).join('~');
}

/**
 * Contributes commands to the palette for as long as the component is mounted.
 *
 * The array itself may be rebuilt on every render — callers should not have to
 * memoise it — so the store is only written when the *content* changes. The
 * newest closures are kept in a ref, which keeps `perform` current without
 * republishing the list.
 */
export function useCommandRegistry(commands: CommandAction[]): void {
    const scope = useRef<string | null>(null);
    scope.current ??= createScope('commands');
    const key = scope.current;

    const latest = useRef(commands);
    latest.current = commands;

    const signature = commandSignature(commands);

    useEffect(() => {
        // `perform` is re-read from the ref at call time so a command registered
        // on an early render still runs against the newest closure.
        const proxied = latest.current.map((command, index) => ({
            ...command,
            perform: () => {
                const current = latest.current[index];

                (current?.id === command.id ? current : command).perform();
            },
        }));

        return registerCommands(key, proxied);
    }, [key, signature]);
}

/** Advertises shortcuts in the help sheet for as long as the component is mounted. */
export function useShortcutHints(hints: ShortcutHint[]): void {
    const scope = useRef<string | null>(null);
    scope.current ??= createScope('hints');
    const key = scope.current;

    const latest = useRef(hints);
    latest.current = hints;

    const signature = hintSignature(hints);

    useEffect(() => registerShortcutHints(key, latest.current), [key, signature]);
}

/** The current, flattened command list. */
export function useCommands(): CommandAction[] {
    return useCommandStore((state) => state.commands);
}

export function useShortcutHintList(): ShortcutHint[] {
    return useCommandStore((state) => state.hints);
}
