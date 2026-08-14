import { create } from 'zustand';

export interface ConfirmOptions {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** `destructive` styles the action and, with `confirmWord`, gates it behind typing. */
    variant?: 'default' | 'destructive';
    /**
     * Requires the user to type this exact word before the action unlocks.
     * Use it for anything irreversible.
     */
    confirmWord?: string;
}

interface ConfirmRequest extends ConfirmOptions {
    id: number;
}

interface ConfirmState {
    request: ConfirmRequest | null;
    open: (options: ConfirmOptions) => Promise<boolean>;
    settle: (id: number, result: boolean) => void;
}

let nextId = 0;

/** Pending resolvers live outside the store so they are never serialised into state. */
const resolvers = new Map<number, (result: boolean) => void>();

export const useConfirmStore = create<ConfirmState>((set, get) => ({
    request: null,

    open: (options) => {
        const previous = get().request;

        // Only one dialog can be on screen; a superseded request resolves false
        // so its awaiting caller is never left hanging.
        if (previous) {
            get().settle(previous.id, false);
        }

        nextId += 1;
        const id = nextId;

        return new Promise<boolean>((resolve) => {
            resolvers.set(id, resolve);
            set({ request: { ...options, id } });
        });
    },

    settle: (id, result) => {
        const resolve = resolvers.get(id);
        resolvers.delete(id);
        resolve?.(result);

        if (get().request?.id === id) {
            set({ request: null });
        }
    },
}));

export type { ConfirmRequest };

/**
 * Returns a function that opens the confirmation dialog and resolves to the
 * user's answer:
 *
 * ```ts
 * if (await confirm({ title: 'Delete project?', variant: 'destructive', confirmWord: 'delete' })) { ... }
 * ```
 *
 * `<ConfirmDialogHost />` must be mounted once in the tree — `AppLayout` does it.
 */
export function useConfirm(): (options: ConfirmOptions) => Promise<boolean> {
    return useConfirmStore((state) => state.open);
}

/** Imperative escape hatch for code outside a component. */
export function confirm(options: ConfirmOptions): Promise<boolean> {
    return useConfirmStore.getState().open(options);
}
