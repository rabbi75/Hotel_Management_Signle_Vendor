import { useDebouncedValue } from '@/components/app-shell/use-debounced-value';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';
import type { ChatIndexProps, ChatMessage, ConversationSummary, TypingSignal } from '@/types/chat';
import { router, usePage } from '@inertiajs/react';
import { MessagesSquare, PanelLeft, Users } from 'lucide-react';
import { lazy, Suspense, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ConversationList } from './conversation-list';
import { MessageComposer } from './message-composer';
import { MessageThread } from './message-thread';
import { NewConversationDialog } from './new-conversation-dialog';
import { useChatRealtime, useWorkspacePresence } from './use-chat-realtime';

const EmojiPicker = lazy(() => import('emoji-picker-react'));

/** Oldest first: the API returns newest first for cursor efficiency. */
function ascending(messages: ChatMessage[]): ChatMessage[] {
    return [...messages].sort((a, b) => a.id - b.id);
}

export default function ChatIndex() {
    const page = usePage<SharedProps & ChatIndexProps>();
    const { conversations, selected, messages: initialMessages, members, limits, can } = page.props;

    const viewerId = page.props.auth.user?.id ?? null;
    const companyId = page.props.auth.company?.id ?? null;

    const { can: allows } = usePermissions();
    const confirm = useConfirm();

    const [search, setSearch] = useState(page.props.search ?? '');
    const debouncedSearch = useDebouncedValue(search, 300);

    const [messages, setMessages] = useState<ChatMessage[]>(ascending(initialMessages?.data ?? []));
    const [cursor, setCursor] = useState<string | null>(initialMessages?.next_cursor ?? null);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [sendError, setSendError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
    const [typing, setTyping] = useState<TypingSignal[]>([]);
    const [receipts, setReceipts] = useState<Record<number, string>>({});
    const [online, setOnline] = useState<number[]>([]);
    const [creating, setCreating] = useState(false);
    const [emojiTarget, setEmojiTarget] = useState<ChatMessage | null>(null);
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const lastTypingPing = useRef(0);
    const selectedId = selected?.id ?? null;

    // A server-driven visit replaces the thread wholesale; local optimistic
    // state must not survive it or the two would drift.
    useEffect(() => {
        setMessages(ascending(initialMessages?.data ?? []));
        setCursor(initialMessages?.next_cursor ?? null);
        setReplyTo(null);
        setSendError(null);
        setTyping([]);
    }, [initialMessages]);

    useEffect(() => {
        if (debouncedSearch === (page.props.search ?? '')) {
            return;
        }

        router.get(
            route('chat.index'),
            { search: debouncedSearch || undefined, conversation: selectedId ?? undefined },
            { preserveState: true, preserveScroll: true, replace: true, only: ['conversations', 'search'] },
        );
        // `page.props.search` is the server's echo of the term; re-running on it
        // would loop the visit back on itself.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedSearch]);

    // Typing indicators expire on their own clock; nothing tells us they stopped.
    useEffect(() => {
        if (typing.length === 0) {
            return;
        }

        const timer = window.setInterval(() => {
            setTyping((current) => current.filter((signal) => signal.expiresAt > Date.now()));
        }, 1000);

        return () => window.clearInterval(timer);
    }, [typing.length]);

    const upsert = useCallback((message: ChatMessage) => {
        setMessages((current) => {
            const index = current.findIndex((existing) => existing.id === message.id);

            if (index === -1) {
                return ascending([...current, message]);
            }

            const next = [...current];
            next[index] = message;

            return next;
        });
    }, []);

    useChatRealtime(selectedId, {
        onSent: upsert,
        onUpdated: upsert,
        onDeleted: (id) => setMessages((current) => current.filter((message) => message.id !== id)),
        onTyping: (signal) =>
            setTyping((current) =>
                [...current.filter((entry) => entry.userId !== signal.userId), signal].filter((entry) => entry.userId !== viewerId),
            ),
        onStoppedTyping: (userId) => setTyping((current) => current.filter((entry) => entry.userId !== userId)),
        onRead: (userId, readAt) => setReceipts((current) => ({ ...current, [userId]: readAt })),
    });

    useWorkspacePresence(companyId, setOnline);

    const participantNames = useMemo(() => {
        const names: Record<number, string> = {};

        selected?.participants.forEach((participant) => {
            names[participant.user_id] = participant.name ?? 'Someone';
        });

        return names;
    }, [selected]);

    function open(conversation: ConversationSummary): void {
        setSidebarOpen(false);
        router.get(
            route('chat.index'),
            { conversation: conversation.id, search: search || undefined },
            { preserveScroll: true, preserveState: false },
        );
    }

    async function loadOlder(): Promise<void> {
        if (!selectedId || !cursor || loadingOlder) {
            return;
        }

        setLoadingOlder(true);

        try {
            const response = await window.axios.get<{ data: ChatMessage[]; next_cursor: string | null }>(
                route('chat.conversations.messages.index', { conversation: selectedId, cursor }),
            );

            setMessages((current) => ascending([...response.data.data, ...current]));
            setCursor(response.data.next_cursor);
        } catch {
            setSendError('Could not load earlier messages.');
        } finally {
            setLoadingOlder(false);
        }
    }

    function send(body: string, files: File[]): void {
        if (!selectedId) {
            return;
        }

        setSending(true);
        setSendError(null);

        router.post(
            route('chat.conversations.messages.store', selectedId),
            {
                body,
                reply_to_id: replyTo?.id ?? null,
                attachments: files,
            },
            {
                forceFormData: files.length > 0,
                preserveScroll: true,
                preserveState: true,
                only: ['conversations', 'messages', 'selected'],
                onError: (errors) => setSendError(errors.body ?? errors.attachments ?? 'Could not send that message.'),
                onFinish: () => {
                    setSending(false);
                    setReplyTo(null);
                },
            },
        );
    }

    function ping(): void {
        if (!selectedId || Date.now() - lastTypingPing.current < 2000) {
            return;
        }

        lastTypingPing.current = Date.now();

        // Fire and forget: a failed typing ping is not worth telling anyone about.
        void window.axios.post(route('chat.conversations.typing', selectedId), { typing: true }).catch(() => undefined);
    }

    function edit(message: ChatMessage, body: string): void {
        router.patch(
            route('chat.messages.update', message.id),
            { body },
            { preserveScroll: true, preserveState: true, only: ['messages', 'conversations'] },
        );
        upsert({ ...message, body, edited: true });
    }

    async function remove(message: ChatMessage): Promise<void> {
        const ok = await confirm({
            title: 'Delete this message?',
            description: 'It will disappear for everyone in the conversation.',
            variant: 'destructive',
            confirmLabel: 'Delete',
        });

        if (!ok) {
            return;
        }

        router.delete(route('chat.messages.destroy', message.id), {
            preserveScroll: true,
            preserveState: true,
            only: ['messages', 'conversations'],
        });
        setMessages((current) => current.filter((entry) => entry.id !== message.id));
    }

    function react(message: ChatMessage, emoji: string): void {
        router.post(
            route('chat.messages.reactions.store', message.id),
            { emoji },
            { preserveScroll: true, preserveState: true, only: ['messages'] },
        );
    }

    const canCreate = can.create && allows('chat.conversations.create');
    const typingNames = typing.map((signal) => signal.userName);

    const sidebar = (
        <ConversationList
            conversations={conversations}
            selectedId={selectedId}
            onSelect={open}
            search={search}
            onSearchChange={setSearch}
            onNew={() => setCreating(true)}
            canCreate={canCreate}
            onlineUserIds={online}
            viewerId={viewerId}
        />
    );

    return (
        <AppLayout title="Chat" breadcrumbs={[{ label: 'Chat' }]}>
            <div className="flex h-[calc(100svh-9rem)] min-h-100 overflow-hidden rounded-lg border border-border bg-card">
                <div className="hidden w-72 shrink-0 border-r border-border md:block">{sidebar}</div>

                <div className="flex min-w-0 flex-1 flex-col">
                    {selected ? (
                        <>
                            <header className="flex items-center gap-3 border-b border-border px-4 py-3">
                                <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
                                    <SheetTrigger asChild>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="md:hidden"
                                            aria-label="Show conversations"
                                        >
                                            <PanelLeft className="size-4" aria-hidden="true" />
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent side="left" className="w-80 p-0">
                                        <SheetTitle className="sr-only">Conversations</SheetTitle>
                                        <SheetDescription className="sr-only">Pick a conversation to open.</SheetDescription>
                                        {sidebar}
                                    </SheetContent>
                                </Sheet>

                                <div className="min-w-0 flex-1">
                                    <h1 className="truncate text-sm font-semibold text-foreground">{selected.title}</h1>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {selected.description ?? `${selected.participants.length} participants`}
                                    </p>
                                </div>

                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button type="button" size="sm" variant="ghost">
                                            <Users className="size-4" aria-hidden="true" />
                                            <span className="sr-only sm:not-sr-only">{selected.participants.length}</span>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent align="end" className="w-64 p-2">
                                        <ul className="space-y-1" aria-label="Participants">
                                            {selected.participants.map((participant) => (
                                                <li key={participant.id} className="flex items-center gap-2 rounded-md px-2 py-1.5">
                                                    <span className="relative">
                                                        <Avatar size="xs">
                                                            {participant.avatar && <AvatarImage src={participant.avatar} alt="" />}
                                                            <AvatarFallback>{participant.initials ?? '??'}</AvatarFallback>
                                                        </Avatar>
                                                        <span
                                                            className={cn(
                                                                'absolute right-0 bottom-0 size-2 rounded-full ring-2 ring-popover',
                                                                online.includes(participant.user_id)
                                                                    ? 'bg-success'
                                                                    : 'bg-muted-foreground/40',
                                                            )}
                                                            role="img"
                                                            aria-label={online.includes(participant.user_id) ? 'Online' : 'Offline'}
                                                        />
                                                    </span>
                                                    <span className="min-w-0 flex-1 truncate text-sm">{participant.name}</span>
                                                    {participant.role !== 'member' && (
                                                        <Badge variant="outline">{participant.role_label}</Badge>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </PopoverContent>
                                </Popover>
                            </header>

                            <MessageThread
                                messages={messages}
                                loadingOlder={loadingOlder}
                                hasMore={cursor !== null}
                                onLoadOlder={() => void loadOlder()}
                                viewerId={viewerId}
                                canDeleteAny={can.delete_any}
                                editWindowMinutes={limits.edit_window_minutes}
                                maxLength={limits.max_message_length}
                                readReceipts={receipts}
                                participantNames={participantNames}
                                onReply={setReplyTo}
                                onEdit={edit}
                                onDelete={(message) => void remove(message)}
                                onReact={react}
                                onOpenEmoji={setEmojiTarget}
                            />

                            <p className="min-h-5 px-4 text-xs text-muted-foreground" aria-live="polite">
                                {typingNames.length > 0 &&
                                    `${typingNames.slice(0, 3).join(', ')} ${typingNames.length === 1 ? 'is' : 'are'} typing…`}
                            </p>

                            <MessageComposer
                                maxLength={limits.max_message_length}
                                maxAttachmentKb={limits.max_attachment_kb}
                                canUpload={can.upload}
                                replyTo={replyTo}
                                onCancelReply={() => setReplyTo(null)}
                                sending={sending}
                                error={sendError}
                                onSend={send}
                                onTyping={ping}
                            />
                        </>
                    ) : (
                        <div className="flex flex-1 flex-col">
                            <div className="border-b border-border p-3 md:hidden">
                                <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
                                    <SheetTrigger asChild>
                                        <Button type="button" variant="outline" size="sm">
                                            <PanelLeft className="size-4" aria-hidden="true" />
                                            Conversations
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent side="left" className="w-80 p-0">
                                        <SheetTitle className="sr-only">Conversations</SheetTitle>
                                        <SheetDescription className="sr-only">Pick a conversation to open.</SheetDescription>
                                        {sidebar}
                                    </SheetContent>
                                </Sheet>
                            </div>

                            <div className="flex flex-1 items-center justify-center p-6">
                                <EmptyState
                                    icon={MessagesSquare}
                                    title="No conversation selected"
                                    description="Pick a conversation from the list, or start a new one."
                                    action={
                                        canCreate ? (
                                            <Button type="button" onClick={() => setCreating(true)}>
                                                New conversation
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <NewConversationDialog open={creating} onOpenChange={setCreating} members={members} />

            <Dialog open={emojiTarget !== null} onOpenChange={(next) => !next && setEmojiTarget(null)}>
                <DialogContent className="w-auto max-w-fit p-4">
                    <DialogHeader>
                        <DialogTitle>Add a reaction</DialogTitle>
                        <DialogDescription>Pick an emoji to add to this message.</DialogDescription>
                    </DialogHeader>
                    <Suspense fallback={<div className="p-4 text-sm text-muted-foreground">Loading emoji…</div>}>
                        <EmojiPicker
                            onEmojiClick={(emoji) => {
                                if (emojiTarget) {
                                    react(emojiTarget, emoji.emoji);
                                }

                                setEmojiTarget(null);
                            }}
                            lazyLoadEmojis
                        />
                    </Suspense>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
