import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTheme } from '@/hooks/use-theme';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/stores/ui-store';
import type { Appearance, AuthenticatedUser } from '@/types';
import { router } from '@inertiajs/react';
import { ChevronsUpDown, Keyboard, LogOut, Monitor, Moon, Palette, Settings, Sun, UserRound } from 'lucide-react';
import { hasRoute, routeUrl } from './routing';

export interface NavUserProps {
    user: AuthenticatedUser;
    /** `sidebar` shows name and email inline; `topbar` is the bare avatar button. */
    variant?: 'sidebar' | 'topbar';
    collapsed?: boolean;
    className?: string;
}

export function NavUser({ user, variant = 'sidebar', collapsed = false, className }: NavUserProps) {
    const { appearance, setAppearance } = useTheme();
    const setShortcutsOpen = useUiStore((state) => state.setShortcutsOpen);

    const profileUrl = routeUrl('profile.show');
    const settingsUrl = routeUrl('settings.index') ?? profileUrl;

    function logout(): void {
        if (!hasRoute('logout')) {
            return;
        }

        router.post(route('logout'));
    }

    const compact = variant === 'topbar' || collapsed;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className={cn(
                        compact ? 'size-9 rounded-full p-0' : 'h-auto w-full justify-start gap-2 px-2 py-2 text-left',
                        !compact && 'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                        className,
                    )}
                    aria-label={`Account menu for ${user.name}`}
                >
                    <UserAvatar user={user} />
                    {!compact && (
                        <>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium">{user.name}</span>
                                <span className="block truncate text-xs text-muted-foreground">{user.email}</span>
                            </span>
                            <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden="true" />
                        </>
                    )}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" side={collapsed ? 'right' : 'top'} className="w-60">
                <DropdownMenuLabel className="flex items-center gap-2 py-2 text-foreground">
                    <UserAvatar user={user} />
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-medium">{user.name}</span>
                        <span className="block truncate text-xs font-normal text-muted-foreground">{user.email}</span>
                    </span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                {profileUrl && (
                    <DropdownMenuItem onSelect={() => router.visit(profileUrl)}>
                        <UserRound className="size-4 opacity-70" aria-hidden="true" />
                        Profile
                    </DropdownMenuItem>
                )}
                {settingsUrl && (
                    <DropdownMenuItem onSelect={() => router.visit(settingsUrl)}>
                        <Settings className="size-4 opacity-70" aria-hidden="true" />
                        Settings
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem onSelect={() => setShortcutsOpen(true)}>
                    <Keyboard className="size-4 opacity-70" aria-hidden="true" />
                    Keyboard shortcuts
                    <DropdownMenuShortcut>?</DropdownMenuShortcut>
                </DropdownMenuItem>

                <DropdownMenuSub>
                    <DropdownMenuSubTrigger>
                        <Palette className="size-4 opacity-70" aria-hidden="true" />
                        Theme
                    </DropdownMenuSubTrigger>
                    <DropdownMenuSubContent>
                        <DropdownMenuRadioGroup value={appearance} onValueChange={(value) => setAppearance(value as Appearance)}>
                            <DropdownMenuRadioItem value="light">
                                <Sun className="size-4 opacity-70" aria-hidden="true" />
                                Light
                            </DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="dark">
                                <Moon className="size-4 opacity-70" aria-hidden="true" />
                                Dark
                            </DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="system">
                                <Monitor className="size-4 opacity-70" aria-hidden="true" />
                                System
                            </DropdownMenuRadioItem>
                        </DropdownMenuRadioGroup>
                    </DropdownMenuSubContent>
                </DropdownMenuSub>

                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={logout}>
                    <LogOut className="size-4" aria-hidden="true" />
                    Log out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function UserAvatar({ user }: { user: AuthenticatedUser }) {
    const src = user.avatar ?? user.avatar_url ?? null;

    return (
        <Avatar size="sm">
            {src && <AvatarImage src={src} alt="" />}
            <AvatarFallback className="text-xs font-semibold">{user.initials}</AvatarFallback>
        </Avatar>
    );
}
