import { resetCommandStore, useCommandStore, type CommandAction } from '@/stores/command-store';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    createScope,
    DEFAULT_COMMAND_GROUP,
    getCommands,
    getShortcutHints,
    groupCommands,
    registerCommands,
    registerShortcutHints,
} from './command-registry';

function command(id: string, overrides: Partial<CommandAction> = {}): CommandAction {
    return {
        id,
        label: id,
        perform: vi.fn(),
        ...overrides,
    };
}

beforeEach(() => {
    resetCommandStore();
});

describe('createScope', () => {
    it('never hands out the same key twice', () => {
        const keys = new Set([createScope(), createScope(), createScope('page')]);

        expect(keys.size).toBe(3);
    });

    it('prefixes the key so a scope is identifiable', () => {
        expect(createScope('settings')).toMatch(/^settings:\d+$/);
    });
});

describe('registerCommands', () => {
    it('publishes commands and removes exactly them on teardown', () => {
        const teardown = registerCommands('page:1', [command('a'), command('b')]);

        expect(getCommands().map((entry) => entry.id)).toEqual(['a', 'b']);

        teardown();

        expect(getCommands()).toEqual([]);
    });

    it('merges contributions from independent scopes', () => {
        registerCommands('page:1', [command('a')]);
        registerCommands('widget:1', [command('b')]);

        expect(
            getCommands()
                .map((entry) => entry.id)
                .sort(),
        ).toEqual(['a', 'b']);
    });

    it('leaves other scopes intact when one unregisters', () => {
        const teardownPage = registerCommands('page:1', [command('a')]);
        registerCommands('widget:1', [command('b')]);

        teardownPage();

        expect(getCommands().map((entry) => entry.id)).toEqual(['b']);
    });

    it('replaces a scope rather than appending when it re-registers', () => {
        registerCommands('page:1', [command('a')]);
        registerCommands('page:1', [command('a'), command('c')]);

        expect(getCommands().map((entry) => entry.id)).toEqual(['a', 'c']);
    });

    it('sorts by descending priority so the important commands surface first', () => {
        registerCommands('page:1', [command('low', { priority: 1 }), command('high', { priority: 10 }), command('none')]);

        expect(getCommands().map((entry) => entry.id)).toEqual(['high', 'low', 'none']);
    });

    it('keeps the store observable through the zustand hook', () => {
        registerCommands('page:1', [command('a')]);

        expect(useCommandStore.getState().commands).toHaveLength(1);
    });

    it('runs the registered handler when a command is performed', () => {
        const perform = vi.fn();
        registerCommands('page:1', [command('a', { perform })]);

        getCommands()[0]?.perform();

        expect(perform).toHaveBeenCalledOnce();
    });
});

describe('registerShortcutHints', () => {
    it('publishes hints under a scope and clears them on teardown', () => {
        const teardown = registerShortcutHints('page:1', [{ keys: ['⌘', 'K'], label: 'Palette', group: 'General' }]);

        expect(getShortcutHints()).toHaveLength(1);

        teardown();

        expect(getShortcutHints()).toEqual([]);
    });
});

describe('groupCommands', () => {
    it('falls back to the default group', () => {
        const grouped = groupCommands([command('a')]);

        expect(grouped).toEqual([[DEFAULT_COMMAND_GROUP, [expect.objectContaining({ id: 'a' })]]]);
    });

    it('buckets by group and preserves first-appearance order', () => {
        const grouped = groupCommands([
            command('a', { group: 'Users' }),
            command('b', { group: 'Billing' }),
            command('c', { group: 'Users' }),
        ]);

        expect(grouped.map(([name]) => name)).toEqual(['Users', 'Billing']);
        expect(grouped[0]?.[1].map((entry) => entry.id)).toEqual(['a', 'c']);
        expect(grouped[1]?.[1].map((entry) => entry.id)).toEqual(['b']);
    });

    it('returns nothing for an empty list', () => {
        expect(groupCommands([])).toEqual([]);
    });
});
