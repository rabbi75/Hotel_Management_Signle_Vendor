import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { getEcho } from '@/lib/echo';
import { cn } from '@/lib/utils';
import type { NotificationItem, SharedProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, BellOff, CheckCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Icon } from './icon';
import { hasRoute, routeUrl } from './routing';

const LEVEL_STYLES: Record<NotificationItem['level'], string> = {
    info: 'bg-info/10 text-info',
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/10 text-warning',
    critical: 'bg-destructive/10 text-destructive',
};

interface NotificationCreatedPayload {
    notification: NotificationItem;
}

export function NotificationBell({ className }: { className?: string }) {
    const { auth, notifications } = usePage<SharedProps>().props;

    const [items, setItems] = useState<NotificationItem[]>(notifications.items);
    const [unread, setUnread] = useState<number>(notifications.unread);

    // Server-rendered props win whenever a navigation delivers a fresh list;
    // the socket only fills the gap between navigations.
    useEffect(() => {
        setItems(notifications.items);
        setUnread(notifications.unread);
    }, [notifications.items, notifications.unread]);

    const userId = auth.user?.id ?? null;

    useEffect(() => {
        if (userId === null) {
            return;
        }

        const echo = getEcho();

        if (!echo) {
            return;
        }

        const channelName = `App.Models.User.${userId}`;

        const channel = echo.private(channelName).listen('NotificationCreated', (payload: NotificationCreatedPayload) => {
            if (!payload?.notification) {
                return;
            }

            setItems((current) => [payload.notification, ...current.filter((item) => item.id !== payload.notification.id)].slice(0, 20));
            setUnread((current) => current + 1);
        });

        return () => {
            channel.stopListening('NotificationCreated');
            echo.leave(channelName);
        };
    }, [userId]);

    function markAsRead(id: string): void {
        setItems((current) => current.map((item) => (item.id === id ? { ...item, read_at: new Date().toISOString() } : item)));
        setUnread((current) => Math.max(0, current - 1));

        if (hasRoute('notifications.read_one')) {
            router.post(route('notifications.read_one', id), {}, { preserveScroll: true, preserveState: true, only: ['notifications'] });
        }
    }

    function markAllAsRead(): void {
        const now = new Date().toISOString();
        setItems((current) => current.map((item) => (item.read_at ? item : { ...item, read_at: now })));
        setUnread(0);

        if (hasRoute('notifications.read_all')) {
            router.post(route('notifications.read_all'), {}, { preserveScroll: true, preserveState: true, only: ['notifications'] });
        }
    }

    const indexUrl = routeUrl('notifications.index');
    const label = unread > 0 ? `Notifications, ${unread} unread` : 'Notifications';

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="icon" className={cn('relative', className)} aria-label={label}>
                    <Bell className="size-4" aria-hidden="true" />
                    {unread > 0 && (
                        <span
                            className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-semibold text-destructive-foreground"
                            aria-hidden="true"
                        >
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent align="end" className="w-[22rem] p-0">
                <div className="flex items-center justify-between px-3 py-2">
                    <p className="text-sm font-semibold">Notifications</p>
                    {unread > 0 && (
                        <Button variant="ghost" size="xs" onClick={markAllAsRead}>
                            <CheckCheck className="size-3.5" aria-hidden="true" />
                            Mark all read
                        </Button>
                    )}
                </div>
                <Separator />

                <div aria-live="polite" aria-atomic="false">
                    {items.length === 0 ? (
                        <EmptyState
                            icon={BellOff}
                            title="You're all caught up"
                            description="New notifications will appear here."
                            className="m-3 border-0 p-6"
                        />
                    ) : (
                        <ScrollArea className="max-h-80">
                            <ul className="divide-y divide-border">
                                {items.map((item) => (
                                    <li key={item.id} className={cn('flex gap-3 px-3 py-3', !item.read_at && 'bg-accent/40')}>
                                        <span
                                            className={cn(
                                                'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
                                                LEVEL_STYLES[item.level],
                                            )}
                                        >
                                            <Icon name={item.icon} className="size-4" />
                                        </span>

                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex items-start gap-2">
                                                <p className="flex-1 text-sm leading-snug font-medium">{item.title}</p>
                                                {!item.read_at && (
                                                    <Badge variant="secondary" className="shrink-0 text-[10px]">
                                                        New
                                                    </Badge>
                                                )}
                                            </div>
                                            {item.body && <p className="text-sm text-muted-foreground">{item.body}</p>}
                                            <div className="flex items-center gap-3 pt-0.5">
                                                <span className="text-xs text-muted-foreground">{item.created_at_human}</span>
                                                {item.action_url && (
                                                    <Link
                                                        href={item.action_url}
                                                        className="text-xs font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {item.action_label ?? 'View'}
                                                    </Link>
                                                )}
                                                {!item.read_at && (
                                                    <button
                                                        type="button"
                                                        onClick={() => markAsRead(item.id)}
                                                        className="text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                                    >
                                                        Mark read
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </ScrollArea>
                    )}
                </div>

                {indexUrl && (
                    <>
                        <Separator />
                        <div className="p-2">
                            <Button variant="ghost" size="sm" className="w-full" asChild>
                                <Link href={indexUrl}>View all notifications</Link>
                            </Button>
                        </div>
                    </>
                )}
            </PopoverContent>
        </Popover>
    );
}
