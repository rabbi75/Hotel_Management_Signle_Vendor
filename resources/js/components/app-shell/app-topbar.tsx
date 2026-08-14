import { Button } from '@/components/ui/button';
import { Kbd } from '@/components/ui/kbd';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/stores/ui-store';
import type { BreadcrumbItem, SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { PanelLeft, Search } from 'lucide-react';
import { Breadcrumbs } from './breadcrumbs';
import { NavUser } from './nav-user';
import { NotificationBell } from './notification-bell';
import { ThemeToggle } from './theme-toggle';
import { TenantSwitcher } from './tenant-switcher';
import { WorkspaceSwitcher } from './workspace-switcher';

export interface AppTopbarProps {
    breadcrumbs?: BreadcrumbItem[];
    className?: string;
}

export function AppTopbar({ breadcrumbs = [], className }: AppTopbarProps) {
    const { auth } = usePage<SharedProps>().props;
    const toggleSidebar = useUiStore((state) => state.toggleSidebar);
    const setSidebarMobileOpen = useUiStore((state) => state.setSidebarMobileOpen);
    const setCommandPaletteOpen = useUiStore((state) => state.setCommandPaletteOpen);

    return (
        <header className={cn('glass sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-border px-3', className)}>
            <Button
                variant="ghost"
                size="icon"
                className="lg:hidden"
                onClick={() => setSidebarMobileOpen(true)}
                aria-label="Open navigation"
            >
                <PanelLeft className="size-4" aria-hidden="true" />
            </Button>
            <Button variant="ghost" size="icon" className="hidden lg:inline-flex" onClick={toggleSidebar} aria-label="Toggle sidebar">
                <PanelLeft className="size-4" aria-hidden="true" />
            </Button>

            <div className="flex min-w-0 flex-1 items-center gap-2">
                <Breadcrumbs items={breadcrumbs} className="hidden sm:flex" />
                <Separator orientation="vertical" className="hidden h-5 sm:block" />
                <TenantSwitcher variant="topbar" />
                <WorkspaceSwitcher variant="topbar" />
            </div>

            <Button
                variant="outline"
                size="sm"
                onClick={() => setCommandPaletteOpen(true)}
                className="hidden h-8 w-56 justify-start gap-2 px-2 text-muted-foreground md:inline-flex xl:w-72"
            >
                <Search className="size-4" aria-hidden="true" />
                <span className="flex-1 text-left">Search…</span>
                <Kbd>⌘K</Kbd>
            </Button>
            <Button variant="ghost" size="icon" className="md:hidden" onClick={() => setCommandPaletteOpen(true)} aria-label="Search">
                <Search className="size-4" aria-hidden="true" />
            </Button>

            <NotificationBell />
            <ThemeToggle />

            {auth.user && <NavUser user={auth.user} variant="topbar" />}
        </header>
    );
}
