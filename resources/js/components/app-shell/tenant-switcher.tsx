import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { CompanySummary, SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { hasRoute, routeUrl } from './routing';

const SEARCH_THRESHOLD = 8;

const ROLE_LABELS: Record<CompanySummary['role'], string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
    guest: 'Guest',
};

export interface TenantSwitcherProps {
    collapsed?: boolean;
    variant?: 'sidebar' | 'topbar';
    className?: string;
}

/** Switches the billing tenant (company). */
export function TenantSwitcher({ collapsed = false, variant = 'sidebar', className }: TenantSwitcherProps) {
    const { auth } = usePage<SharedProps>().props;
    const [query, setQuery] = useState('');
    const [switching, setSwitching] = useState<string | null>(null);

    const companies = auth.companies;
    const active = auth.company;

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (!term) {
            return companies;
        }

        return companies.filter((company) => company.name.toLowerCase().includes(term) || company.slug.toLowerCase().includes(term));
    }, [companies, query]);

    function switchTo(company: CompanySummary): void {
        if (company.uuid === active?.uuid || !hasRoute('companies.switch')) {
            return;
        }

        setSwitching(company.uuid);

        router.post(
            route('companies.switch', company.uuid),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSwitching(null),
            },
        );
    }

    const createUrl = routeUrl('companies.create');
    const isTopbar = variant === 'topbar';
    const label = active ? `Tenant: ${active.name}. Switch tenant` : 'Select a tenant';

    const trigger = isTopbar ? (
        <Button variant="outline" className={cn('h-8 max-w-44 gap-2 px-2', className)} aria-label={label}>
            <TenantAvatar company={active} />
            <span className="hidden truncate text-xs font-medium sm:block">{active?.name ?? 'No tenant'}</span>
            <ChevronsUpDown className="size-3.5 shrink-0 opacity-50" aria-hidden="true" />
        </Button>
    ) : (
        <Button
            variant="ghost"
            className={cn(
                'h-auto w-full justify-start gap-2 px-2 py-2 text-left hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                collapsed && 'justify-center px-0',
            )}
            aria-label={label}
        >
            <TenantAvatar company={active} />
            {!collapsed && (
                <>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-medium">{active?.name ?? 'No tenant'}</span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {active ? ROLE_LABELS[active.role] : 'Choose one to continue'}
                        </span>
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden="true" />
                </>
            )}
        </Button>
    );

    return (
        <DropdownMenu>
            {collapsed && !isTopbar ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
                    </TooltipTrigger>
                    <TooltipContent side="right">{active?.name ?? 'No tenant'}</TooltipContent>
                </Tooltip>
            ) : (
                <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            )}

            <DropdownMenuContent align="start" side={collapsed && !isTopbar ? 'right' : 'bottom'} className={cn('w-64', !isTopbar && className)}>
                <DropdownMenuLabel>Tenants</DropdownMenuLabel>

                {companies.length > SEARCH_THRESHOLD && (
                    <div className="px-1 pb-1">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                            <Input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Find a tenant"
                                aria-label="Find a tenant"
                                className="h-8 pl-7 text-sm"
                                onKeyDown={(event) => event.stopPropagation()}
                            />
                        </div>
                    </div>
                )}

                <div className="max-h-72 overflow-y-auto">
                    {filtered.length === 0 && <p className="px-2 py-4 text-center text-sm text-muted-foreground">No tenants found.</p>}

                    {filtered.map((company) => {
                        const isActive = company.uuid === active?.uuid;

                        return (
                            <DropdownMenuItem
                                key={company.uuid}
                                onSelect={() => switchTo(company)}
                                aria-current={isActive ? 'true' : undefined}
                                disabled={switching !== null}
                                className="gap-2"
                            >
                                <TenantAvatar company={company} />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm">{company.name}</span>
                                    <span className="block truncate text-xs text-muted-foreground">{ROLE_LABELS[company.role]}</span>
                                </span>
                                {isActive && <Check className="size-4 shrink-0 text-primary" aria-hidden="true" />}
                            </DropdownMenuItem>
                        );
                    })}
                </div>

                {createUrl && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => router.visit(createUrl)}>
                            <Plus className="size-4" aria-hidden="true" />
                            Create tenant
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function TenantAvatar({ company }: { company: CompanySummary | null }) {
    return (
        <Avatar size="sm" className="rounded-md">
            {company?.logo && <AvatarImage src={company.logo} alt="" />}
            <AvatarFallback className="rounded-md bg-primary/10 text-xs font-semibold text-primary">
                {company?.initials ?? '—'}
            </AvatarFallback>
        </Avatar>
    );
}
