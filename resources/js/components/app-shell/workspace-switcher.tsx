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
import type { SharedProps, WorkspaceSummary } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { hasRoute, routeUrl } from './routing';

const SEARCH_THRESHOLD = 8;

const ROLE_LABELS: Record<NonNullable<WorkspaceSummary['role']>, string> = {
    admin: 'Admin',
    manager: 'Manager',
    member: 'Member',
};

export interface WorkspaceSwitcherProps {
    collapsed?: boolean;
    variant?: 'sidebar' | 'topbar';
    className?: string;
}

/** Switches the operational workspace inside the current tenant. */
export function WorkspaceSwitcher({ collapsed = false, variant = 'sidebar', className }: WorkspaceSwitcherProps) {
    const { auth } = usePage<SharedProps>().props;
    const [query, setQuery] = useState('');
    const [switching, setSwitching] = useState<string | null>(null);

    const workspaces = auth.workspaces ?? [];
    const active = auth.workspace ?? null;

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (!term) {
            return workspaces;
        }

        return workspaces.filter(
            (workspace) => workspace.name.toLowerCase().includes(term) || workspace.slug.toLowerCase().includes(term),
        );
    }, [workspaces, query]);

    function switchTo(workspace: WorkspaceSummary): void {
        if (workspace.uuid === active?.uuid || !hasRoute('operational-workspaces.switch')) {
            return;
        }

        setSwitching(workspace.uuid);

        router.post(
            route('operational-workspaces.switch', workspace.uuid),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSwitching(null),
            },
        );
    }

    const createUrl = routeUrl('operational-workspaces.create');
    const isTopbar = variant === 'topbar';
    const label = active ? `Workspace: ${active.name}. Switch workspace` : 'Select a workspace';

    const trigger = isTopbar ? (
        <Button variant="ghost" className={cn('h-8 max-w-52 gap-2 px-2', className)} aria-label={label}>
            <WorkspaceAvatar workspace={active} />
            <span className="hidden truncate text-sm font-medium sm:block">{active?.name ?? 'No workspace'}</span>
            <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden="true" />
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
            <WorkspaceAvatar workspace={active} />
            {!collapsed && (
                <>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-medium">{active?.name ?? 'No workspace'}</span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {active?.role ? ROLE_LABELS[active.role] : 'Choose one to continue'}
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
                    <TooltipContent side="right">{active?.name ?? 'No workspace'}</TooltipContent>
                </Tooltip>
            ) : (
                <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            )}

            <DropdownMenuContent align="start" side={collapsed && !isTopbar ? 'right' : 'bottom'} className={cn('w-64', !isTopbar && className)}>
                <DropdownMenuLabel>Workspaces</DropdownMenuLabel>

                {workspaces.length > SEARCH_THRESHOLD && (
                    <div className="px-1 pb-1">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                            <Input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Find a workspace"
                                aria-label="Find a workspace"
                                className="h-8 pl-7 text-sm"
                                onKeyDown={(event) => event.stopPropagation()}
                            />
                        </div>
                    </div>
                )}

                <div className="max-h-72 overflow-y-auto">
                    {filtered.length === 0 && <p className="px-2 py-4 text-center text-sm text-muted-foreground">No workspaces found.</p>}

                    {filtered.map((workspace) => {
                        const isActive = workspace.uuid === active?.uuid;

                        return (
                            <DropdownMenuItem
                                key={workspace.uuid}
                                onSelect={() => switchTo(workspace)}
                                aria-current={isActive ? 'true' : undefined}
                                disabled={switching !== null}
                                className="gap-2"
                            >
                                <WorkspaceAvatar workspace={workspace} />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm">{workspace.name}</span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {workspace.role ? ROLE_LABELS[workspace.role] : workspace.is_default ? 'Default' : 'Member'}
                                    </span>
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
                            Create workspace
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function WorkspaceAvatar({ workspace }: { workspace: WorkspaceSummary | null }) {
    return (
        <Avatar size="sm" className="rounded-md">
            {workspace?.logo && <AvatarImage src={workspace.logo} alt="" />}
            <AvatarFallback className="rounded-md bg-primary/10 text-xs font-semibold text-primary">
                {workspace?.initials ?? '—'}
            </AvatarFallback>
        </Avatar>
    );
}
