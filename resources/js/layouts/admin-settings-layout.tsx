import { Icon } from '@/components/app-shell/icon';
import { PageHeader } from '@/components/app-shell/page-header';
import { currentRouteName, isNavItemActive, routeUrl } from '@/components/app-shell/routing';
import { Separator } from '@/components/ui/separator';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

/*
|------------------------------------------------------------------------------
| Installation settings
|------------------------------------------------------------------------------
|
| The console's own settings shell. Every panel behind it writes the system
| scope — SMTP credentials, object-storage keys, third-party secrets, the
| password policy, maintenance mode — so it is deliberately not the tenant
| settings layout with a different colour: it hangs off the operator console,
| and only an operator ever sees it.
|
| A link whose route is not registered, or whose permission the operator lacks,
| is dropped rather than rendered inert.
|
*/

interface SettingsLink {
    label: string;
    icon: string;
    route: string;
    activeWhen: string[];
    /** Hint shown under the label; these panels are not self-explanatory. */
    hint?: string;
}

const LINKS: SettingsLink[] = [
    { label: 'General', icon: 'settings', route: 'admin.settings.index', activeWhen: ['admin.settings.index', 'admin.settings.general.*'], hint: 'Name, contact, signups' },
    { label: 'Localization', icon: 'globe', route: 'admin.settings.localization.index', activeWhen: ['admin.settings.localization.*'], hint: 'Locale, timezone, currency' },
    { label: 'Appearance', icon: 'palette', route: 'admin.settings.appearance.index', activeWhen: ['admin.settings.appearance.*'], hint: 'Logo, brand colour, CSS' },
    { label: 'Mail', icon: 'mail', route: 'admin.settings.mail.index', activeWhen: ['admin.settings.mail.*'], hint: 'SMTP and sender' },
    { label: 'Storage', icon: 'database', route: 'admin.settings.storage.index', activeWhen: ['admin.settings.storage.*'], hint: 'Disks and credentials' },
    { label: 'API keys', icon: 'key-round', route: 'admin.settings.api_keys.index', activeWhen: ['admin.settings.api_keys.*'], hint: 'Third-party secrets' },
    { label: 'AI', icon: 'sparkles', route: 'admin.settings.ai.index', activeWhen: ['admin.settings.ai.*'], hint: 'LLM providers and credits' },
    { label: 'Security', icon: 'lock', route: 'admin.settings.security.index', activeWhen: ['admin.settings.security.*'], hint: 'Passwords and sessions' },
    { label: 'Maintenance', icon: 'cog', route: 'admin.settings.maintenance.index', activeWhen: ['admin.settings.maintenance.*'], hint: 'Take the site offline' },
];

interface ResolvedLink extends SettingsLink {
    href: string;
}

function resolveLinks(): ResolvedLink[] {
    const resolved: ResolvedLink[] = [];

    for (const link of LINKS) {
        const href = routeUrl(link.route);

        if (href !== null) {
            resolved.push({ ...link, href });
        }
    }

    return resolved;
}

export interface AdminSettingsLayoutProps {
    title: string;
    description?: string;
    breadcrumbs?: BreadcrumbItem[];
    actions?: ReactNode;
    children: ReactNode;
}

export function AdminSettingsLayout({ title, description, breadcrumbs, actions, children }: AdminSettingsLayoutProps) {
    const current = currentRouteName();
    const resolved = resolveLinks();

    const crumbs: BreadcrumbItem[] = breadcrumbs ?? [{ label: 'Settings' }, { label: title }];

    return (
        <AppLayout title={`${title} settings`} breadcrumbs={crumbs}>
            <div className="mx-auto w-full max-w-5xl space-y-6">
                <PageHeader
                    title="System settings"
                    description="Configuration for this hotel installation."
                    actions={actions}
                />
                <Separator />

                <div className="flex flex-col gap-6 lg:flex-row lg:gap-10">
                    <nav
                        aria-label="Installation settings"
                        className="-mx-1 flex shrink-0 scrollbar-none gap-1 overflow-x-auto px-1 pb-1 lg:mx-0 lg:w-60 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0"
                    >
                        {resolved.map((link) => {
                            const active = isNavItemActive(link.activeWhen, current);

                            return (
                                <Link
                                    key={link.route}
                                    href={link.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex shrink-0 items-start gap-2.5 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                                        active
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                                    )}
                                >
                                    <Icon name={link.icon} className="mt-0.5 size-4 shrink-0" />
                                    <span className="min-w-0">
                                        <span className="block">{link.label}</span>
                                        {link.hint && (
                                            <span className="hidden text-xs font-normal text-muted-foreground lg:block">{link.hint}</span>
                                        )}
                                    </span>
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

export default AdminSettingsLayout;
