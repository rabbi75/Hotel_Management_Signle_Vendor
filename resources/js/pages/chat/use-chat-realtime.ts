import { getEcho } from '@/lib/echo';
import type { ChatMessage, TypingSignal } from '@/types/chat';
import { useEffect, useRef } from 'react';

interface MessagePayload {
    message: ChatMessage;
}

interface DeletedPayload {
    message_id: number;
}

interface TypingPayload {
    user_id: number;
    user_name: string;
    typing: boolean;
    ttl: number;
}

interface ReadPayload {
    user_id: number;
    read_at: string;
    last_message_id: number | null;
}

export interface ChatRealtimeHandlers {
    onSent: (message: ChatMessage) => void;
    onUpdated: (message: ChatMessage) => void;
    onDeleted: (messageId: number) => void;
    onTyping: (signal: TypingSignal) => void;
    onStoppedTyping: (userId: number) => void;
    onRead: (userId: number, readAt: string) => void;
}

/**
 * Subscribe to one conversation's private channel.
 *
 * The kit ships without Pusher credentials, so `getEcho()` returns null and
 * every one of these subscriptions is simply skipped: chat still works, it just
 * does not update until another visit. Handlers are held in a ref so a parent
 * re-render never tears the subscription down and rebuilds it — which would
 * drop events in the gap.
 */
export function useChatRealtime(conversationId: number | null, handlers: ChatRealtimeHandlers): void {
    const handlersRef = useRef(handlers);
    handlersRef.current = handlers;

    useEffect(() => {
        if (conversationId === null) {
            return;
        }

        const echo = getEcho();

        if (!echo) {
            return;
        }

        const name = `conversation.${conversationId}`;
        const channel = echo.private(name);

        channel.listen('.message.sent', (payload: MessagePayload) => handlersRef.current.onSent(payload.message));
        channel.listen('.message.updated', (payload: MessagePayload) => handlersRef.current.onUpdated(payload.message));
        channel.listen('.message.deleted', (payload: DeletedPayload) => handlersRef.current.onDeleted(payload.message_id));

        channel.listen('.user.typing', (payload: TypingPayload) => {
            if (!payload.typing) {
                handlersRef.current.onStoppedTyping(payload.user_id);

                return;
            }

            handlersRef.current.onTyping({
                userId: payload.user_id,
                userName: payload.user_name,
                expiresAt: Date.now() + Math.max(1, payload.ttl) * 1000,
            });
        });

        channel.listen('.message.read', (payload: ReadPayload) => handlersRef.current.onRead(payload.user_id, payload.read_at));

        return () => {
            echo.leave(name);
        };
    }, [conversationId]);
}

export interface PresenceMember {
    id: number;
    name: string;
    initials: string;
    avatar: string | null;
}

/**
 * Track who is online in the workspace, for the presence dots.
 *
 * Returns nothing and calls `onChange` instead, so the caller owns the state
 * shape; with no broadcaster configured it never fires and every dot stays off.
 */
export function useWorkspacePresence(companyId: number | null, onChange: (ids: number[]) => void): void {
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    useEffect(() => {
        if (companyId === null) {
            return;
        }

        const echo = getEcho();

        if (!echo) {
            return;
        }

        const name = `presence.company.${companyId}`;
        const online = new Set<number>();

        const publish = () => onChangeRef.current([...online]);

        echo.join(name)
            .here((members: PresenceMember[]) => {
                online.clear();
                members.forEach((member) => online.add(member.id));
                publish();
            })
            .joining((member: PresenceMember) => {
                online.add(member.id);
                publish();
            })
            .leaving((member: PresenceMember) => {
                online.delete(member.id);
                publish();
            });

        return () => {
            echo.leave(name);
            onChangeRef.current([]);
        };
    }, [companyId]);
}
