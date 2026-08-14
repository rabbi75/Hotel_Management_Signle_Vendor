import { BrandLogo } from '@/components/app-shell/brand-logo';
import { Breadcrumbs } from '@/components/app-shell/breadcrumbs';
import { Icon } from '@/components/app-shell/icon';
import { currentRouteName, isNavItemActive, routeUrl } from '@/components/app-shell/routing';
import { ThemeToggle } from '@/components/app-shell/theme-toggle';
import { Button } from '@/components/ui/button';
import { ConfirmDialogHost } from '@/components/feedback/confirm-dialog';
import { ErrorBoundary } from '@/components/feedback/error-boundary';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/stores/ui-store';
import type { BreadcrumbItem, SharedProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { LogOut, PanelLeft, ShieldCheck } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';

/*
|------------------------------------------------------------------------------
| The operator console shell
|------------------------------------------------------------------------------
|
| A world of its own: fixed sidebar, console-only chrome, and no bridge back to
| a tenant app — an operator has no workspace. The "Admins" and "Settings"
| links only resolve for an admin who may manage the roster.
|
*/

interface AdminLink {
    label: string;
    icon: string;
    routes: string[];
    activeWhen: string[];
    /** Permission (or super-admin) required to see the link. */
    permission?: string;
}

interface AdminSection {
    /** Null renders the links bare, without a heading — the top-level items. */
    label: string | null;
    links: AdminLink[];
}

/*
| Grouped rather than flat: a single list of a dozen destinations is a wall, and
| the console's areas are genuinely distinct — who is paying us, what we sell
| them, what the site says, who we are. A section with no visible links renders
| nothing, so an operator without the billing permissions never sees a Billing
| heading with an empty body under it.
*/
const SECTIONS: AdminSection[] = [
    {
        label: null,
        links: [
            { label: 'Overview', icon: 'layout-dashboard', routes: ['admin.dashboard'], activeWhen: ['admin.dashboard'] },
            { label: 'Tenants', icon: 'building-2', routes: ['admin.tenants.index'], activeWhen: ['admin.tenants.*'] },
        ],
    },
    {
        label: 'Support',
        links: [
            { label: 'Users', icon: 'users-round', routes: ['admin.users.index'], activeWhen: ['admin.users.*'], permission: 'platform.users.manage' },
            { label: 'Tickets', icon: 'inbox', routes: ['admin.tickets.index'], activeWhen: ['admin.tickets.*'], permission: 'platform.tickets.view' },
        ],
    },
    {
        label: 'Billing',
        links: [
            { label: 'Revenue', icon: 'trending-up', routes: ['admin.revenue.index'], activeWhen: ['admin.revenue.*'], permission: 'platform.revenue.view' },
            { label: 'Invoices', icon: 'receipt', routes: ['admin.invoices.index'], activeWhen: ['admin.invoices.*'], permission: 'platform.invoices.view' },
            { label: 'Collections', icon: 'circle-alert', routes: ['admin.dunning.index'], activeWhen: ['admin.dunning.*'], permission: 'platform.invoices.view' },
            { label: 'Gateway events', icon: 'webhook', routes: ['admin.billing.events.index'], activeWhen: ['admin.billing.events.*'], permission: 'platform.invoices.view' },
            { label: 'Payment gateways', icon: 'credit-card', routes: ['admin.gateways.index'], activeWhen: ['admin.gateways.*'], permission: 'platform.gateways.manage' },
        ],
    },
    {
        label: 'Catalogue',
        links: [
            { label: 'Plans', icon: 'boxes', routes: ['admin.plans.index'], activeWhen: ['admin.plans.*'] },
            { label: 'Coupons', icon: 'ticket-percent', routes: ['admin.coupons.index'], activeWhen: ['admin.coupons.*'] },
        ],
    },
    {
        label: 'Content',
        links: [
            { label: 'Pages', icon: 'file-text', routes: ['admin.cms.pages.index'], activeWhen: ['admin.cms.pages.*', 'admin.cms.blocks.*'], permission: 'platform.pages.manage' },
            { label: 'Menus', icon: 'list-tree', routes: ['admin.cms.menus.index'], activeWhen: ['admin.cms.menus.*', 'admin.cms.menu-items.*'], permission: 'platform.content.manage' },
            { label: 'Posts', icon: 'book', routes: ['admin.blog.posts.index'], activeWhen: ['admin.blog.posts.*'], permission: 'platform.content.manage' },
            { label: 'Categories', icon: 'folder', routes: ['admin.blog.categories.index'], activeWhen: ['admin.blog.categories.*'], permission: 'platform.content.manage' },
            { label: 'Tags', icon: 'tag', routes: ['admin.blog.tags.index'], activeWhen: ['admin.blog.tags.*'], permission: 'platform.content.manage' },
            { label: 'Comments', icon: 'message-square', routes: ['admin.blog.comments.index'], activeWhen: ['admin.blog.comments.*'], permission: 'platform.content.manage' },
            { label: 'Media', icon: 'images', routes: ['admin.media.index'], activeWhen: ['admin.media.*'], permission: 'platform.content.manage' },
            { label: 'SEO', icon: 'globe', routes: ['admin.seo.index'], activeWhen: ['admin.seo.*'], permission: 'platform.content.manage' },
            { label: 'Appearance', icon: 'palette', routes: ['admin.appearance.index'], activeWhen: ['admin.appearance.*'], permission: 'platform.appearance.manage' },
        ],
    },
    {
        label: 'System',
        links: [
            { label: 'Settings', icon: 'settings', routes: ['admin.settings.index'], activeWhen: ['admin.settings.*'], permission: 'platform.settings.view' },
            { label: 'AI', icon: 'sparkles', routes: ['admin.settings.ai.index'], activeWhen: ['admin.settings.ai.*'], permission: 'platform.settings.ai' },
            { label: 'AI usage', icon: 'gauge', routes: ['admin.ai.usage'], activeWhen: ['admin.ai.usage'], permission: 'platform.tenants.view' },
            { label: 'Audit', icon: 'shield-alert', routes: ['admin.audit.index'], activeWhen: ['admin.audit.*'], permission: 'platform.audit.view' },
            { label: 'Announcements', icon: 'megaphone', routes: ['admin.announcements.index'], activeWhen: ['admin.announcements.*'], permission: 'platform.announcements.manage' },
            { label: 'Email templates', icon: 'mail', routes: ['admin.email-templates.index'], activeWhen: ['admin.email-templates.*'], permission: 'platform.email_templates.manage' },
            { label: 'Admins', icon: 'shield-check', routes: ['admin.admins.index'], activeWhen: ['admin.admins.*'], permission: 'platform.admins.manage' },
        ],
    },
];

interface ResolvedLink extends AdminLink {
    href: string;
}

interface ResolvedSection {
    label: string | null;
    links: ResolvedLink[];
}

/**
 * Sections with their links narrowed to what this operator may reach, dropping
 * any that end up empty. A link whose route is not registered — a module the
 * kit was built without — is skipped rather than rendered inert.
 */
function resolveSections(granted: string[], isSuperAdmin: boolean): ResolvedSection[] {
    const sections: ResolvedSection[] = [];

    for (const section of SECTIONS) {
        const links: ResolvedLink[] = [];

        for (const link of section.links) {
            if (link.permission !== undefined && !isSuperAdmin && !granted.includes(link.permission)) {
                continue;
            }

            for (const name of link.routes) {
                const href = routeUrl(name);

                if (href === null) {
                    continue;
                }

                links.push({ ...link, href });
                break;
            }
        }

        if (links.length > 0) {
            sections.push({ label: section.label, links });
        }
    }

    return sections;
}

function AdminSidebar({
    granted,
    isSuperAdmin,
    onNavigate,
}: {
    granted: string[];
    isSuperAdmin: boolean;
    onNavigate?: () => void;
}) {
    const current = currentRouteName();
    const sections = useMemo(() => resolveSections(granted, isSuperAdmin), [granted, isSuperAdmin]);

    return (
        <div className="flex h-full flex-col bg-sidebar text-sidebar-foreground">
            <div className="flex items-center gap-2 px-4 py-4">
                <BrandLogo
                    variant="full"
                    fallbackIcon={ShieldCheck}
                    markClassName="size-8 rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
                    imageClassName="h-7"
                />
            </div>

            <nav aria-label="Admin" className="flex flex-1 flex-col gap-4 overflow-y-auto px-2 py-2">
                {sections.map((section, index) => (
                    <div key={section.label ?? `top-${index}`} className="flex flex-col gap-1">
                        {section.label && (
                            <p className="px-3 pt-1 pb-0.5 text-xs font-medium tracking-wide text-sidebar-foreground/50 uppercase">
                                {section.label}
                            </p>
                        )}

                        {section.links.map((link) => {
                            const active = isNavItemActive(link.activeWhen, current);

                            return (
                                <Link
                                    key={link.label}
                                    href={link.href}
                                    onClick={onNavigate}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                        active
                                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                            : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground',
                                    )}
                                >
                                    <Icon name={link.icon} className="size-4" />
                                    {link.label}
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </nav>
        </div>
    );
}

function AdminTopbar({ breadcrumbs }: { breadcrumbs: BreadcrumbItem[] }) {
    const setSidebarMobileOpen = useUiStore((state) => state.setSidebarMobileOpen);

    function logout(): void {
        router.post(route('admin.logout'));
    }

    return (
        <header className="glass sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-border px-3">
            <Button variant="ghost" size="icon" className="lg:hidden" onClick={() => setSidebarMobileOpen(true)} aria-label="Open navigation">
                <PanelLeft className="size-4" aria-hidden="true" />
            </Button>

            <BrandLogo variant="full" fallbackIcon={ShieldCheck} className="shrink-0 lg:hidden" imageClassName="h-7" />

            <div className="min-w-0 flex-1">
                <Breadcrumbs items={breadcrumbs} className="hidden sm:flex" />
            </div>

            <ThemeToggle />

            <Button variant="ghost" size="sm" className="gap-2" onClick={logout}>
                <LogOut className="size-4" aria-hidden="true" />
                <span className="hidden sm:inline">Sign out</span>
            </Button>
        </header>
    );
}

export interface AdminLayoutProps {
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
    children: ReactNode;
}

export function AdminLayout({ breadcrumbs = [], title, children }: AdminLayoutProps) {
    const { auth } = usePage<SharedProps>().props;
    const sidebarMobileOpen = useUiStore((state) => state.sidebarMobileOpen);
    const setSidebarMobileOpen = useUiStore((state) => state.setSidebarMobileOpen);

    useFlashToasts();

    const isSuperAdmin = Boolean(auth.admin?.is_super_admin);
    const crumbs: BreadcrumbItem[] = breadcrumbs.length > 0 ? breadcrumbs : [{ label: 'Admin' }];

    return (
        <TooltipProvider delayDuration={200}>
            {title && <Head title={title} />}

            <div className="flex min-h-svh bg-background">
                <aside className="sticky top-0 hidden h-svh w-64 shrink-0 border-r border-sidebar-border lg:block" aria-label="Sidebar">
                    <AdminSidebar granted={auth.permissions} isSuperAdmin={isSuperAdmin} />
                </aside>

                <Sheet open={sidebarMobileOpen} onOpenChange={setSidebarMobileOpen}>
                    <SheetContent side="left" className="w-72 p-0 lg:hidden">
                        <SheetTitle className="sr-only">Admin navigation</SheetTitle>
                        <SheetDescription className="sr-only">Administer tenants, users and plans.</SheetDescription>
                        <AdminSidebar granted={auth.permissions} isSuperAdmin={isSuperAdmin} onNavigate={() => setSidebarMobileOpen(false)} />
                    </SheetContent>
                </Sheet>

                <div className="flex min-w-0 flex-1 flex-col">
                    <AdminTopbar breadcrumbs={crumbs} />

                    {crumbs.length > 0 && (
                        <div className="border-b border-border px-4 py-2 sm:hidden">
                            <Breadcrumbs items={crumbs} />
                        </div>
                    )}

                    <main className="flex-1 overflow-x-hidden p-4 md:p-6">
                        <ErrorBoundary>
                            {children}
                        </ErrorBoundary>
                    </main>
                </div>
            </div>

            <ConfirmDialogHost />
        </TooltipProvider>
    );
}
