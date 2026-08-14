import { AppSidebar } from '@/components/app-shell/app-sidebar';
import { AppTopbar } from '@/components/app-shell/app-topbar';
import { Breadcrumbs } from '@/components/app-shell/breadcrumbs';
import { ImpersonationBanner } from '@/components/app-shell/impersonation-banner';
import { ShortcutsDialog } from '@/components/app-shell/shortcuts-dialog';
import { CommandPalette } from '@/components/command-palette/command-palette';
import { ConfirmDialogHost } from '@/components/feedback/confirm-dialog';
import { ErrorBoundary } from '@/components/feedback/error-boundary';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useShortcutHints } from '@/hooks/use-command-registry';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import { useShortcuts, type Shortcut } from '@/hooks/use-shortcuts';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/stores/ui-store';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useMemo, type ReactNode } from 'react';

export interface AppLayoutProps {
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
    children: ReactNode;
}

export function AppLayout({ breadcrumbs = [], title, children }: AppLayoutProps) {
    const sidebarOpen = useUiStore((state) => state.sidebarOpen);
    const sidebarMobileOpen = useUiStore((state) => state.sidebarMobileOpen);
    const setSidebarMobileOpen = useUiStore((state) => state.setSidebarMobileOpen);
    const toggleSidebar = useUiStore((state) => state.toggleSidebar);
    const setCommandPaletteOpen = useUiStore((state) => state.setCommandPaletteOpen);
    const setShortcutsOpen = useUiStore((state) => state.setShortcutsOpen);

    useFlashToasts();

    const shortcuts = useMemo<Shortcut[]>(
        () => [
            {
                key: 'k',
                meta: true,
                allowInInput: true,
                label: 'Open the command palette',
                group: 'General',
                handler: () => setCommandPaletteOpen(true),
            },
            {
                key: '/',
                label: 'Open the command palette',
                group: 'General',
                handler: () => setCommandPaletteOpen(true),
            },
            {
                key: '?',
                shift: true,
                label: 'Show keyboard shortcuts',
                group: 'General',
                handler: () => setShortcutsOpen(true),
            },
            {
                key: 'b',
                meta: true,
                allowInInput: true,
                label: 'Toggle the sidebar',
                group: 'Navigation',
                handler: toggleSidebar,
            },
        ],
        [setCommandPaletteOpen, setShortcutsOpen, toggleSidebar],
    );

    useShortcuts(shortcuts);

    useShortcutHints([
        { keys: ['⌘', 'K'], label: 'Open the command palette', group: 'General' },
        { keys: ['/'], label: 'Focus search', group: 'General' },
        { keys: ['?'], label: 'Show keyboard shortcuts', group: 'General' },
        { keys: ['⌘', 'B'], label: 'Toggle the sidebar', group: 'Navigation' },
        { keys: ['Esc'], label: 'Close the current overlay', group: 'General' },
    ]);

    return (
        <TooltipProvider delayDuration={200}>
            {title && <Head title={title} />}

            <ImpersonationBanner />

            <div className="flex min-h-svh bg-background">
                <aside
                    className={cn(
                        'sticky top-0 hidden h-svh shrink-0 border-r border-sidebar-border transition-[width] duration-200 ease-out-quint lg:block',
                        sidebarOpen ? 'w-64' : 'w-16',
                    )}
                    aria-label="Sidebar"
                >
                    <AppSidebar collapsed={!sidebarOpen} />
                </aside>

                <Sheet open={sidebarMobileOpen} onOpenChange={setSidebarMobileOpen}>
                    <SheetContent side="left" className="w-72 p-0 lg:hidden">
                        <SheetTitle className="sr-only">Navigation</SheetTitle>
                        <SheetDescription className="sr-only">Application sections and your account.</SheetDescription>
                        <AppSidebar onNavigate={() => setSidebarMobileOpen(false)} />
                    </SheetContent>
                </Sheet>

                <div className="flex min-w-0 flex-1 flex-col">
                    <AppTopbar breadcrumbs={breadcrumbs} />

                    {breadcrumbs.length > 0 && (
                        <div className="border-b border-border px-4 py-2 sm:hidden">
                            <Breadcrumbs items={breadcrumbs} />
                        </div>
                    )}

                    <main id="main-content" className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                        <ErrorBoundary>{children}</ErrorBoundary>
                    </main>
                </div>
            </div>

            <CommandPalette />
            <ShortcutsDialog />
            <ConfirmDialogHost />
        </TooltipProvider>
    );
}

export default AppLayout;
