/*
|------------------------------------------------------------------------------
| Chat
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\Chat\Http\Resources\* and the props the chat controllers
| attach alongside them. Any change on the PHP side must be reflected here.
|
*/

export type ConversationType = 'direct' | 'group' | 'channel';
export type MessageType = 'text' | 'system' | 'attachment';
export type ParticipantRole = 'owner' | 'admin' | 'member';

export interface ChatUserSummary {
    id: number;
    name: string;
    initials: string;
    avatar: string | null;
}

export interface ChatMemberOption {
    id: number;
    name: string;
    initials: string;
    avatar: string | null;
    job_title: string | null;
}

export interface ConversationParticipant {
    id: number;
    user_id: number;
    name: string | null;
    initials: string | null;
    avatar: string | null;
    role: ParticipantRole;
    role_label: string;
    muted: boolean;
    joined_at: string | null;
    last_read_at: string | null;
}

export interface MessageAttachment {
    id: number;
    name: string;
    mime_type: string | null;
    size: number;
    is_image: boolean;
    url: string | null;
    thumbnail: string | null;
}

export interface MessageReaction {
    emoji: string;
    count: number;
    user_ids: number[];
}

export interface MessageReplyPreview {
    id: number;
    body: string;
    author: string | null;
}

export interface ChatMessage {
    id: number;
    conversation_id: number;
    user_id: number | null;
    type: MessageType;
    body: string;
    author: ChatUserSummary | null;
    reply_to: MessageReplyPreview | null;
    attachments: MessageAttachment[];
    reactions: MessageReaction[];
    edited: boolean;
    edited_at: string | null;
    created_at: string | null;
    /** Only present on search results, which span conversations. */
    conversation_title?: string;
}

export interface ConversationSummary {
    id: number;
    type: ConversationType;
    type_label: string;
    name: string | null;
    title: string;
    description: string | null;
    created_by: number | null;
    participants: ConversationParticipant[];
    participant_ids: number[];
    last_message: { id: number; body: string; author: string | null; created_at: string | null } | null;
    last_message_at: string | null;
    unread_count: number;
    muted: boolean;
    created_at: string | null;
}

export interface MessagePage {
    data: ChatMessage[];
    next_cursor: string | null;
}

export interface ChatLimits {
    max_message_length: number;
    max_attachment_kb: number;
    typing_ttl: number;
    edit_window_minutes: number;
    per_page: number;
}

export interface ChatIndexProps {
    conversations: ConversationSummary[];
    selected: ConversationSummary | null;
    messages: MessagePage | null;
    members: ChatMemberOption[];
    search: string | null;
    limits: ChatLimits;
    can: { create: boolean; upload: boolean; delete_any: boolean };
    [key: string]: unknown;
}

/** Someone currently typing, with the moment their indicator expires. */
export interface TypingSignal {
    userId: number;
    userName: string;
    expiresAt: number;
}
