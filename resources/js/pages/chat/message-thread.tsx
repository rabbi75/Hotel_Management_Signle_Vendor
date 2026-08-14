import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Spinner } from '@/components/ui/spinner';
import type { ChatMessage } from '@/types/chat';
import { useVirtualizer } from '@tanstack/react-virtual';
import { ArrowDown, MessageSquareDashed } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { MessageItem } from './message-item';

type Row = { kind: 'day'; key: string; label: string } | { kind: 'message'; key: string; message: ChatMessage; showAuthor: boolean };

export interface MessageThreadProps {
    messages: ChatMessage[];
    loadingOlder: boolean;
    hasMore: boolean;
    onLoadOlder: () => void;
    viewerId: number | null;
    canDeleteAny: boolean;
    editWindowMinutes: number;
    maxLength: number;
    /** Participant name => the id of the newest message they have read. */
    readReceipts: Record<number, string>;
    participantNames: Record<number, string>;
    onReply: (message: ChatMessage) => void;
    onEdit: (message: ChatMessage, body: string) => void;
    onDelete: (message: ChatMessage) => void;
    onReact: (message: ChatMessage, emoji: string) => void;
    onOpenEmoji: (message: ChatMessage) => void;
}

/** Messages from the same author inside this window are drawn as one run. */
const GROUPING_WINDOW_MS = 5 * 60_000;

function dayLabel(iso: string | null): string {
    if (!iso) {
        return 'Unknown date';
    }

    const date = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today.getTime() - 86_400_000);

    if (date.toDateString() === today.toDateString()) {
        return 'Today';
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return 'Yesterday';
    }

    return date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });
}

function buildRows(messages: ChatMessage[]): Row[] {
    const rows: Row[] = [];
    let lastDay: string | null = null;
    let previous: ChatMessage | null = null;

    for (const message of messages) {
        const day = message.created_at ? new Date(message.created_at).toDateString() : 'unknown';

        if (day !== lastDay) {
            rows.push({ kind: 'day', key: `day-${day}`, label: dayLabel(message.created_at) });
            lastDay = day;
            previous = null;
        }

        const sameAuthor = previous !== null && previous.user_id === message.user_id && message.type !== 'system';
        const closeInTime =
            previous?.created_at && message.created_at
                ? new Date(message.created_at).getTime() - new Date(previous.created_at).getTime() < GROUPING_WINDOW_MS
                : false;

        rows.push({
            kind: 'message',
            key: `message-${message.id}`,
            message,
            showAuthor: !(sameAuthor && closeInTime),
        });

        previous = message;
    }

    return rows;
}

export function MessageThread({
    messages,
    loadingOlder,
    hasMore,
    onLoadOlder,
    viewerId,
    canDeleteAny,
    editWindowMinutes,
    maxLength,
    readReceipts,
    participantNames,
    onReply,
    onEdit,
    onDelete,
    onReact,
    onOpenEmoji,
}: MessageThreadProps) {
    const scrollRef = useRef<HTMLDivElement | null>(null);
    const [atBottom, setAtBottom] = useState(true);
    const previousCount = useRef(messages.length);

    const rows = useMemo(() => buildRows(messages), [messages]);

    const virtualizer = useVirtualizer({
        count: rows.length,
        getScrollElement: () => scrollRef.current,
        estimateSize: () => 68,
        overscan: 12,
        getItemKey: (index) => rows[index]?.key ?? index,
    });

    const scrollToBottom = useCallback(() => {
        const element = scrollRef.current;

        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    }, []);

    // A new message only yanks the viewport down when the reader was already at
    // the bottom; otherwise it would rip them away from what they were reading.
    useLayoutEffect(() => {
        if (messages.length > previousCount.current && atBottom) {
            scrollToBottom();
        }

        previousCount.current = messages.length;
    }, [messages.length, atBottom, scrollToBottom]);

    useEffect(() => {
        scrollToBottom();
        // Only on a conversation switch: the first message's id identifies the thread.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [messages[0]?.conversation_id]);

    function handleScroll(): void {
        const element = scrollRef.current;

        if (!element) {
            return;
        }

        const distanceFromBottom = element.scrollHeight - element.scrollTop - element.clientHeight;
        setAtBottom(distanceFromBottom < 80);

        if (element.scrollTop < 120 && hasMore && !loadingOlder) {
            onLoadOlder();
        }
    }

    const newest = messages.at(-1);

    function readersOf(message: ChatMessage): string[] {
        if (!newest || message.id !== newest.id || message.user_id !== viewerId) {
            return [];
        }

        return Object.entries(readReceipts)
            .filter(([userId]) => Number(userId) !== viewerId)
            .map(([userId]) => participantNames[Number(userId)] ?? 'Someone');
    }

    if (messages.length === 0) {
        return (
            <div className="flex flex-1 items-center justify-center p-6">
                <EmptyState
                    icon={MessageSquareDashed}
                    title="No messages yet"
                    description="Say something to get the conversation started."
                />
            </div>
        );
    }

    const items = virtualizer.getVirtualItems();

    return (
        <div className="relative min-h-0 flex-1">
            <div ref={scrollRef} onScroll={handleScroll} className="h-full overflow-y-auto" tabIndex={0} aria-label="Message history">
                {loadingOlder && (
                    <div className="flex justify-center py-3">
                        <Spinner size="sm" aria-label="Loading earlier messages" />
                    </div>
                )}

                <ul
                    role="log"
                    aria-live="polite"
                    aria-relevant="additions"
                    className="relative w-full list-none"
                    style={{ height: virtualizer.getTotalSize() }}
                >
                    {items.map((item) => {
                        const row = rows[item.index];

                        if (!row) {
                            return null;
                        }

                        return (
                            <li
                                key={item.key}
                                data-index={item.index}
                                ref={virtualizer.measureElement}
                                className="absolute top-0 left-0 w-full"
                                style={{ transform: `translateY(${item.start}px)` }}
                            >
                                {row.kind === 'day' ? (
                                    <div className="flex items-center gap-3 px-4 py-3">
                                        <span className="h-px flex-1 bg-border" aria-hidden="true" />
                                        <span className="text-xs font-medium text-muted-foreground">{row.label}</span>
                                        <span className="h-px flex-1 bg-border" aria-hidden="true" />
                                    </div>
                                ) : (
                                    <MessageItem
                                        message={row.message}
                                        showAuthor={row.showAuthor}
                                        viewerId={viewerId}
                                        canDeleteAny={canDeleteAny}
                                        editWindowMinutes={editWindowMinutes}
                                        maxLength={maxLength}
                                        readBy={readersOf(row.message)}
                                        onReply={onReply}
                                        onEdit={onEdit}
                                        onDelete={onDelete}
                                        onReact={onReact}
                                        onOpenEmoji={onOpenEmoji}
                                    />
                                )}
                            </li>
                        );
                    })}
                </ul>
            </div>

            {!atBottom && (
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    className="absolute right-4 bottom-4 shadow-sm"
                    onClick={() => {
                        scrollToBottom();
                        setAtBottom(true);
                    }}
                >
                    <ArrowDown className="size-4" aria-hidden="true" />
                    Jump to latest
                </Button>
            )}
        </div>
    );
}
