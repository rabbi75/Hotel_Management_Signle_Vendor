import { create } from 'zustand';

interface UiState {
    sidebarOpen: boolean;
    sidebarMobileOpen: boolean;
    commandPaletteOpen: boolean;
    shortcutsOpen: boolean;

    setSidebarOpen: (open: boolean) => void;
    toggleSidebar: () => void;
    setSidebarMobileOpen: (open: boolean) => void;
    setCommandPaletteOpen: (open: boolean) => void;
    toggleCommandPalette: () => void;
    setShortcutsOpen: (open: boolean) => void;
}

const SIDEBAR_COOKIE = 'sidebar_state';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

function persistSidebar(open: boolean): void {
    // Read back by HandleAppearance so the server renders the sidebar in the
    // right state and it does not visibly snap open on load.
    document.cookie = `${SIDEBAR_COOKIE}=${open};path=/;max-age=${COOKIE_MAX_AGE};SameSite=Lax`;
}

export const useUiStore = create<UiState>((set, get) => ({
    sidebarOpen: true,
    sidebarMobileOpen: false,
    commandPaletteOpen: false,
    shortcutsOpen: false,

    setSidebarOpen: (open) => {
        persistSidebar(open);
        set({ sidebarOpen: open });
    },
    toggleSidebar: () => {
        const next = !get().sidebarOpen;
        persistSidebar(next);
        set({ sidebarOpen: next });
    },
    setSidebarMobileOpen: (open) => set({ sidebarMobileOpen: open }),
    setCommandPaletteOpen: (open) => set({ commandPaletteOpen: open }),
    toggleCommandPalette: () => set({ commandPaletteOpen: !get().commandPaletteOpen }),
    setShortcutsOpen: (open) => set({ shortcutsOpen: open }),
}));
