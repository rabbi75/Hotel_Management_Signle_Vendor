import { useCommandStore, type CommandAction, type ShortcutHint } from '@/stores/command-store';

export type { CommandAction, ShortcutHint };

export const DEFAULT_COMMAND_GROUP = 'Actions';

let scopeCounter = 0;

/** A process-unique scope key, so two instances of one component never collide. */
export function createScope(prefix = 'scope'): string {
    scopeCounter += 1;

    return `${prefix}:${scopeCounter}`;
}

/**
 * Adds commands under a scope and returns the matching teardown.
 *
 * Registering the same scope again replaces its previous contribution rather
 * than appending, which is what keeps a re-render from duplicating entries.
 */
export function registerCommands(scope: string, commands: CommandAction[]): () => void {
    useCommandStore.getState().registerCommands(scope, commands);

    return () => useCommandStore.getState().unregisterCommands(scope);
}

export function registerShortcutHints(scope: string, hints: ShortcutHint[]): () => void {
    useCommandStore.getState().registerHints(scope, hints);

    return () => useCommandStore.getState().unregisterHints(scope);
}

export function getCommands(): CommandAction[] {
    return useCommandStore.getState().commands;
}

export function getShortcutHints(): ShortcutHint[] {
    return useCommandStore.getState().hints;
}

/** Groups commands for rendering, preserving the order each group first appears in. */
export function groupCommands(commands: CommandAction[]): [string, CommandAction[]][] {
    const groups = new Map<string, CommandAction[]>();

    for (const command of commands) {
        const key = command.group ?? DEFAULT_COMMAND_GROUP;
        const bucket = groups.get(key);

        if (bucket) {
            bucket.push(command);

            continue;
        }

        groups.set(key, [command]);
    }

    return [...groups.entries()];
}
