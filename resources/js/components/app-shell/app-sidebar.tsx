import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { NavItem, NavSection, SharedProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { BrandLogo } from './brand-logo';
import { Icon } from './icon';
import { currentRouteName, isNavItemActive, routeUrl } from './routing';

export interface AppSidebarProps {
    /** Icon-only rail. Labels move into tooltips. */
    collapsed?: boolean;
    /** Set when rendered inside the mobile Sheet so links can close it. */
    onNavigate?: () => void;
    className?: string;
}

export function AppSidebar({ collapsed = false, onNavigate, className }: AppSidebarProps) {
    const { navigation, name } = usePage<SharedProps>().props;
    const current = currentRouteName();
    const dashboardUrl = routeUrl('dashboard');

    return (
        <div className={cn('flex h-full flex-col bg-sidebar text-sidebar-foreground', className)}>
            <div className={cn('flex shrink-0 items-center justify-center border-b border-sidebar-border px-3 py-3', collapsed && 'px-2')}>
                {dashboardUrl ? (
                    <Link
                        href={dashboardUrl}
                        className="rounded-md outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                        aria-label={`${name} home`}
                        onClick={onNavigate}
                    >
                        <BrandLogo
                            variant={collapsed ? 'icon' : 'full'}
                            imageClassName={collapsed ? 'size-8 rounded-md' : 'h-7'}
                            markClassName="size-8"
                            nameClassName="text-sm font-semibold tracking-tight"
                        />
                    </Link>
                ) : (
                    <BrandLogo
                        variant={collapsed ? 'icon' : 'full'}
                        imageClassName={collapsed ? 'size-8 rounded-md' : 'h-7'}
                        markClassName="size-8"
                        nameClassName="text-sm font-semibold tracking-tight"
                    />
                )}
            </div>

            <ScrollArea className="flex-1">
                <nav aria-label="Main" className={cn('flex flex-col gap-4 px-2 py-3', collapsed && 'items-stretch')}>
                    {navigation.map((section) => (
                        <NavSectionBlock
                            key={section.label}
                            section={section}
                            collapsed={collapsed}
                            current={current}
                            onNavigate={onNavigate}
                        />
                    ))}
                </nav>
            </ScrollArea>
        </div>
    );
}

interface SectionProps {
    section: NavSection;
    collapsed: boolean;
    current: string | null;
    onNavigate?: () => void;
}

function NavSectionBlock({ section, collapsed, current, onNavigate }: SectionProps) {
    if (section.items.length === 0) {
        return null;
    }

    return (
        <div className="space-y-1">
            {section.label &&
                (collapsed ? (
                    <Separator className="mx-auto my-2 w-6 bg-sidebar-border" />
                ) : (
                    <p className="px-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">{section.label}</p>
                ))}

            <ul className="space-y-0.5">
                {section.items.map((item) => (
                    <li key={`${section.label}-${item.label}`}>
                        <NavEntry item={item} collapsed={collapsed} current={current} onNavigate={onNavigate} />
                    </li>
                ))}
            </ul>
        </div>
    );
}

interface EntryProps {
    item: NavItem;
    collapsed: boolean;
    current: string | null;
    onNavigate?: () => void;
}

function NavEntry({ item, collapsed, current, onNavigate }: EntryProps) {
    const selfActive = isNavItemActive(item.activeWhen, current);
    const childActive = useMemo(() => item.children.some((child) => isNavItemActive(child.activeWhen, current)), [item.children, current]);
    const [open, setOpen] = useState(childActive);

    if (item.children.length > 0) {
        // A collapsed rail has no room for a disclosure, so the parent becomes a
        // tooltip menu of its own label and the children stay reachable via the
        // expanded sidebar or the command palette.
        if (collapsed) {
            return (
                <NavTooltip label={item.label} collapsed>
                    <NavRow item={item} active={selfActive || childActive} collapsed onNavigate={onNavigate} />
                </NavTooltip>
            );
        }

        return (
            <Collapsible open={open || childActive} onOpenChange={setOpen}>
                <CollapsibleTrigger
                    className={cn(rowClasses(selfActive || childActive), 'w-full [&[data-state=open]>svg:last-child]:rotate-90')}
                >
                    <Icon name={item.icon} className="size-4" />
                    <span className="flex-1 truncate text-left">{item.label}</span>
                    {item.badge && <NavBadge value={item.badge} />}
                    <ChevronRight className="size-4 shrink-0 opacity-60 transition-transform" aria-hidden="true" />
                </CollapsibleTrigger>

                <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                    <ul className="mt-0.5 ml-4 space-y-0.5 border-l border-sidebar-border pl-2">
                        {item.children.map((child) => (
                            <li key={child.label}>
                                <NavRow
                                    item={child}
                                    active={isNavItemActive(child.activeWhen, current)}
                                    collapsed={false}
                                    onNavigate={onNavigate}
                                    showIcon={false}
                                />
                            </li>
                        ))}
                    </ul>
                </CollapsibleContent>
            </Collapsible>
        );
    }

    return (
        <NavTooltip label={item.label} collapsed={collapsed}>
            <NavRow item={item} active={selfActive} collapsed={collapsed} onNavigate={onNavigate} />
        </NavTooltip>
    );
}

function NavTooltip({ label, collapsed, children }: { label: string; collapsed: boolean; children: ReactNode }) {
    if (!collapsed) {
        return <>{children}</>;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent side="right">{label}</TooltipContent>
        </Tooltip>
    );
}

function rowClasses(active: boolean): string {
    return cn(
        'flex items-center gap-2 rounded-md px-2 py-2 text-sm font-medium transition-colors outline-none',
        'focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:ring-offset-2 focus-visible:ring-offset-sidebar',
        active
            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
            : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/60 hover:text-sidebar-accent-foreground',
    );
}

interface RowProps {
    item: NavItem;
    active: boolean;
    collapsed: boolean;
    onNavigate?: () => void;
    showIcon?: boolean;
}

function NavRow({ item, active, collapsed, onNavigate, showIcon = true }: RowProps) {
    const content = (
        <>
            {showIcon && <Icon name={item.icon} className="size-4" />}
            {!collapsed && <span className="flex-1 truncate">{item.label}</span>}
            {!collapsed && item.badge && <NavBadge value={item.badge} />}
            {collapsed && <span className="sr-only">{item.label}</span>}
        </>
    );

    const classes = cn(rowClasses(active), collapsed && 'justify-center px-0', active && 'relative');

    if (!item.href) {
        return (
            <span className={cn(classes, 'cursor-default opacity-60')} aria-disabled="true">
                {content}
            </span>
        );
    }

    return (
        <Link href={item.href} className={classes} aria-current={active ? 'page' : undefined} onClick={onNavigate}>
            {content}
        </Link>
    );
}

function NavBadge({ value }: { value: string }) {
    return (
        <Badge variant="secondary" className="ml-auto px-1.5 text-[10px]">
            {value}
        </Badge>
    );
}
