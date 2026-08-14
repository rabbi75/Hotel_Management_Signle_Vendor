import { Icon } from '@/components/app-shell/icon';
import { PageHeader } from '@/components/app-shell/page-header';
import { currentRouteName, isNavItemActive, routeUrl } from '@/components/app-shell/routing';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppLayout } from './app-layout';

interface SettingsLink {
    label: string;
    icon: string;
    /** Tried in order; the first registered route wins. */
    routes: string[];
}

const LINKS: SettingsLink[] = [
    { label: 'Profile', icon: 'user-round', routes: ['profile.show', 'profile.edit'] },
    { label: 'Password', icon: 'lock', routes: ['settings.password.edit', 'password.edit', 'profile.password.edit'] },
    { label: 'Two-factor', icon: 'shield-check', routes: ['settings.two-factor.show', 'two-factor.show'] },
    { label: 'Sessions', icon: 'monitor', routes: ['settings.sessions.index', 'sessions.index'] },
    { label: 'Login history', icon: 'history', routes: ['settings.login-history', 'login-history.index'] },
    // No Appearance entry: branding is installation-wide and now lives in the
    // operator console. A member's own light/dark choice is the topbar toggle.
    {
        label: 'Notifications',
        icon: 'bell',
        routes: ['settings.notifications.index', 'notifications.preferences.edit', 'notification-settings.edit'],
    },
];

interface ResolvedLink extends SettingsLink {
    name: string;
    href: string;
    activeWhen: string[];
}

/**
 * Resolves the first registered candidate and derives its active pattern from
 * the route's own namespace, so `settings.two-factor.show` also highlights on
 * `settings.two-factor.recovery-codes` without a hand-maintained list.
 */
function resolveLinks(): ResolvedLink[] {
    const resolved: ResolvedLink[] = [];

    for (const link of LINKS) {
        for (const name of link.routes) {
            const href = routeUrl(name);

            if (href === null) {
                continue;
            }

            const namespace = name.split('.').slice(0, -1).join('.');

            resolved.push({
                ...link,
                name,
                href,
                activeWhen: namespace ? [name, `${namespace}.*`] : [name],
            });

            break;
        }
    }

    return resolved;
}

export interface SettingsLayoutProps {
    title: string;
    description?: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    actions?: ReactNode;
    children: ReactNode;
}

export function SettingsLayout({ title, description, breadcrumbs, actions, children }: SettingsLayoutProps) {
    const current = currentRouteName();

    const resolved = resolveLinks();

    const crumbs: BreadcrumbItem[] = breadcrumbs ?? [{ label: 'Settings' }, { label: title }];

    return (
        <AppLayout title={title} breadcrumbs={crumbs}>
            <div className="mx-auto w-full max-w-5xl space-y-6">
                <PageHeader title="Settings" description="Manage your account, security and preferences." actions={actions} />
                <Separator />

                <div className="flex flex-col gap-6 lg:flex-row lg:gap-10">
                    <nav
                        aria-label="Settings"
                        className="-mx-1 flex shrink-0 scrollbar-none gap-1 overflow-x-auto px-1 pb-1 lg:mx-0 lg:w-56 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0"
                    >
                        {resolved.map((link) => {
                            const active = isNavItemActive(link.activeWhen, current);

                            return (
                                <Link
                                    key={link.name}
                                    href={link.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                                        active
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                                    )}
                                >
                                    <Icon name={link.icon} className="size-4" />
                                    {link.label}
                                </Link>
                            );
                        })}
                    </nav>

                    <section className="min-w-0 flex-1 space-y-6" aria-labelledby="settings-panel-title">
                        <div className="space-y-1">
                            <h2 id="settings-panel-title" className="text-lg font-semibold tracking-tight">
                                {title}
                            </h2>
                            {description && <p className="text-sm text-muted-foreground">{description}</p>}
                        </div>
                        {children}
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}

export default SettingsLayout;
