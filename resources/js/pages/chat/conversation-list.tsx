import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { ConversationSummary } from '@/types/chat';
import { Hash, MessagesSquare, Plus, Search, Users } from 'lucide-react';
import { useId, type ChangeEvent } from 'react';

interface ConversationListProps {
    conversations: ConversationSummary[];
    selectedId: number | null;
    onSelect: (conversation: ConversationSummary) => void;
    search: string;
    onSearchChange: (value: string) => void;
    onNew: () => void;
    canCreate: boolean;
    onlineUserIds: number[];
    viewerId: number | null;
}

function relativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const diff = Date.now() - date.getTime();
    const minutes = Math.round(diff / 60000);

    if (minutes < 1) {
        return 'now';
    }

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours}h`;
    }

    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

function ConversationIcon({ conversation }: { conversation: ConversationSummary }) {
    if (conversation.type === 'channel') {
        return <Hash className="size-4 text-muted-foreground" aria-hidden="true" />;
    }

    if (conversation.type === 'group') {
        return <Users className="size-4 text-muted-foreground" aria-hidden="true" />;
    }

    return null;
}

export function ConversationList({
    conversations,
    selectedId,
    onSelect,
    search,
    onSearchChange,
    onNew,
    canCreate,
    onlineUserIds,
    viewerId,
}: ConversationListProps) {
    const searchId = useId();
    const online = new Set(onlineUserIds);

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex items-center gap-2 border-b border-border p-3">
                <div className="relative min-w-0 flex-1">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <label htmlFor={searchId} className="sr-only">
                        Search conversations
                    </label>
                    <Input
                        id={searchId}
                        type="search"
                        value={search}
                        placeholder="Search conversations"
                        className="pl-8"
                        onChange={(event: ChangeEvent<HTMLInputElement>) => onSearchChange(event.target.value)}
                    />
                </div>

                {canCreate && (
                    <Button type="button" size="icon" variant="outline" onClick={onNew} aria-label="Start a conversation">
                        <Plus className="size-4" aria-hidden="true" />
                    </Button>
                )}
            </div>

            {conversations.length === 0 ? (
                <div className="p-3">
                    <EmptyState
                        icon={MessagesSquare}
                        title={search ? 'No matching conversations' : 'No conversations yet'}
                        description={search ? 'Try a different search term.' : 'Start one to get talking.'}
                        action={
                            canCreate && !search ? (
                                <Button type="button" size="sm" onClick={onNew}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New conversation
                                </Button>
                            ) : undefined
                        }
                    />
                </div>
            ) : (
                <ul className="min-h-0 flex-1 overflow-y-auto py-1" aria-label="Conversations">
                    {conversations.map((conversation) => {
                        const active = conversation.id === selectedId;
                        const other = conversation.participants.find((participant) => participant.user_id !== viewerId);
                        const isOnline = other ? online.has(other.user_id) : false;

                        return (
                            <li key={conversation.id}>
                                <button
                                    type="button"
                                    onClick={() => onSelect(conversation)}
                                    aria-current={active ? 'true' : undefined}
                                    className={cn(
                                        'flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                                        active ? 'bg-accent' : 'hover:bg-accent/60',
                                    )}
                                >
                                    <span className="relative shrink-0">
                                        <Avatar size="sm">
                                            {other?.avatar && <AvatarImage src={other.avatar} alt="" />}
                                            <AvatarFallback>
                                                {other?.initials ?? conversation.title.slice(0, 2).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        {conversation.type === 'direct' && (
                                            <span
                                                className={cn(
                                                    'absolute right-0 bottom-0 size-2.5 rounded-full ring-2 ring-background',
                                                    isOnline ? 'bg-success' : 'bg-muted-foreground/40',
                                                )}
                                                aria-label={isOnline ? 'Online' : 'Offline'}
                                                role="img"
                                            />
                                        )}
                                    </span>

                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-1.5">
                                            <ConversationIcon conversation={conversation} />
                                            <span className="truncate text-sm font-medium text-foreground">{conversation.title}</span>
                                            <span className="ml-auto shrink-0 text-xs text-muted-foreground">
                                                {relativeTime(conversation.last_message_at)}
                                            </span>
                                        </span>
                                        <span className="mt-0.5 flex items-center gap-2">
                                            <span className="truncate text-xs text-muted-foreground">
                                                {conversation.last_message?.body ?? 'No messages yet'}
                                            </span>
                                            {conversation.unread_count > 0 && (
                                                <Badge className="ml-auto shrink-0" aria-label={`${conversation.unread_count} unread`}>
                                                    {conversation.unread_count > 99 ? '99+' : conversation.unread_count}
                                                </Badge>
                                            )}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
