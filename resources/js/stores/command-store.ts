import { create } from 'zustand';

/** A command any page can contribute to the palette while it is mounted. */
export interface CommandAction {
    id: string;
    label: string;
    /** Palette group heading. Defaults to `Actions` when omitted. */
    group?: string;
    /** Kebab-case lucide icon name. */
    icon?: string | null;
    subtitle?: string;
    /** Extra terms cmdk should match on beyond the label. */
    keywords?: string[];
    /** Display-only key hint, e.g. `['⌘', 'N']`. */
    shortcut?: string[];
    /** Higher sorts first inside its group. */
    priority?: number;
    perform: () => void;
}

/** A keyboard shortcut advertised in the help sheet. */
export interface ShortcutHint {
    keys: string[];
    label: string;
    group: string;
}

interface CommandState {
    commands: CommandAction[];
    hints: ShortcutHint[];

    registerCommands: (scope: string, commands: CommandAction[]) => void;
    unregisterCommands: (scope: string) => void;
    registerHints: (scope: string, hints: ShortcutHint[]) => void;
    unregisterHints: (scope: string) => void;
}

/**
 * Scope ownership is tracked separately from the flattened lists so a page can
 * unregister exactly what it added, even when two scopes contribute a command
 * with the same id.
 */
const commandScopes = new Map<string, CommandAction[]>();
const hintScopes = new Map<string, ShortcutHint[]>();

function flattenCommands(): CommandAction[] {
    return [...commandScopes.values()].flat().sort((a, b) => (b.priority ?? 0) - (a.priority ?? 0));
}

function flattenHints(): ShortcutHint[] {
    return [...hintScopes.values()].flat();
}

export const useCommandStore = create<CommandState>((set) => ({
    commands: [],
    hints: [],

    registerCommands: (scope, commands) => {
        commandScopes.set(scope, commands);
        set({ commands: flattenCommands() });
    },
    unregisterCommands: (scope) => {
        commandScopes.delete(scope);
        set({ commands: flattenCommands() });
    },
    registerHints: (scope, hints) => {
        hintScopes.set(scope, hints);
        set({ hints: flattenHints() });
    },
    unregisterHints: (scope) => {
        hintScopes.delete(scope);
        set({ hints: flattenHints() });
    },
}));

/** Test-only reset; the scope maps live outside the store and survive `setState`. */
export function resetCommandStore(): void {
    commandScopes.clear();
    hintScopes.clear();
    useCommandStore.setState({ commands: [], hints: [] });
}
