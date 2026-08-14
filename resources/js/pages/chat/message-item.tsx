import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { ChatMessage } from '@/types/chat';
import { Check, CornerUpLeft, Ellipsis, FileText, Paperclip, Pencil, SmilePlus, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';

export interface MessageItemProps {
    message: ChatMessage;
    /** False when this message continues a run from the same author. */
    showAuthor: boolean;
    viewerId: number | null;
    canDeleteAny: boolean;
    editWindowMinutes: number;
    maxLength: number;
    /** Ids of participants whose read marker is at or past this message. */
    readBy: string[];
    onReply: (message: ChatMessage) => void;
    onEdit: (message: ChatMessage, body: string) => void;
    onDelete: (message: ChatMessage) => void;
    onReact: (message: ChatMessage, emoji: string) => void;
    onOpenEmoji: (message: ChatMessage) => void;
}

const QUICK_REACTIONS = ['👍', '🎉', '❤️'];

function formatTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function withinEditWindow(message: ChatMessage, minutes: number): boolean {
    if (!message.created_at) {
        return false;
    }

    return Date.now() - new Date(message.created_at).getTime() < minutes * 60_000;
}

export function MessageItem({
    message,
    showAuthor,
    viewerId,
    canDeleteAny,
    editWindowMinutes,
    maxLength,
    readBy,
    onReply,
    onEdit,
    onDelete,
    onReact,
    onOpenEmoji,
}: MessageItemProps) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(message.body);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    const mine = viewerId !== null && message.user_id === viewerId;
    const mayEdit = mine && message.type !== 'system' && withinEditWindow(message, editWindowMinutes);
    const mayDelete = mine || canDeleteAny;

    useEffect(() => {
        if (editing) {
            textareaRef.current?.focus();
        }
    }, [editing]);

    if (message.type === 'system') {
        return (
            <div className="px-4 py-1.5 text-center text-xs text-muted-foreground">
                <span>{message.body}</span>
            </div>
        );
    }

    function commitEdit(): void {
        const next = draft.trim();

        if (next !== '' && next !== message.body) {
            onEdit(message, next);
        }

        setEditing(false);
    }

    function onEditKeyDown(event: KeyboardEvent<HTMLTextAreaElement>): void {
        if (event.key === 'Escape') {
            event.preventDefault();
            setDraft(message.body);
            setEditing(false);
        }

        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            commitEdit();
        }
    }

    return (
        <article className={cn('group/message relative px-4 hover:bg-accent/40', showAuthor ? 'pt-3 pb-1' : 'py-0.5')}>
            <div className="flex gap-3">
                <div className="w-8 shrink-0">
                    {showAuthor ? (
                        <Avatar size="sm">
                            {message.author?.avatar && <AvatarImage src={message.author.avatar} alt="" />}
                            <AvatarFallback>{message.author?.initials ?? '??'}</AvatarFallback>
                        </Avatar>
                    ) : (
                        <span className="sr-only">{message.author?.name}</span>
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    {showAuthor && (
                        <p className="flex flex-wrap items-baseline gap-2">
                            <span className="text-sm font-semibold text-foreground">{message.author?.name ?? 'Unknown'}</span>
                            <time className="text-xs text-muted-foreground" dateTime={message.created_at ?? undefined}>
                                {formatTime(message.created_at)}
                            </time>
                        </p>
                    )}

                    {message.reply_to && (
                        <p className="mt-1 flex items-center gap-1.5 border-l-2 border-border pl-2 text-xs text-muted-foreground">
                            <CornerUpLeft className="size-3 shrink-0" aria-hidden="true" />
                            <span className="font-medium">{message.reply_to.author ?? 'Someone'}</span>
                            <span className="truncate">{message.reply_to.body}</span>
                        </p>
                    )}

                    {editing ? (
                        <div className="mt-1 space-y-2">
                            <Textarea
                                ref={textareaRef}
                                value={draft}
                                rows={2}
                                maxLength={maxLength}
                                aria-label="Edit message"
                                onChange={(event) => setDraft(event.target.value)}
                                onKeyDown={onEditKeyDown}
                            />
                            <div className="flex gap-2">
                                <Button type="button" size="sm" onClick={commitEdit}>
                                    <Check className="size-4" aria-hidden="true" />
                                    Save
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        setDraft(message.body);
                                        setEditing(false);
                                    }}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm break-words whitespace-pre-wrap text-foreground">
                            {message.body}
                            {message.edited && (
                                <span className="ml-1.5 text-xs text-muted-foreground" title="Edited">
                                    (edited)
                                </span>
                            )}
                        </p>
                    )}

                    {message.attachments.length > 0 && (
                        <ul className="mt-2 flex flex-wrap gap-2" aria-label="Attachments">
                            {message.attachments.map((attachment) => (
                                <li key={attachment.id}>
                                    <a
                                        href={attachment.url ?? '#'}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex items-center gap-2 rounded-md border border-border p-1.5 text-xs text-foreground hover:bg-accent"
                                    >
                                        {attachment.is_image && attachment.thumbnail ? (
                                            <img
                                                src={attachment.thumbnail}
                                                alt={attachment.name}
                                                className="size-16 rounded object-cover"
                                            />
                                        ) : (
                                            <FileText className="size-4 text-muted-foreground" aria-hidden="true" />
                                        )}
                                        <span className="max-w-40 truncate">{attachment.name}</span>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}

                    {message.reactions.length > 0 && (
                        <ul className="mt-1.5 flex flex-wrap gap-1" aria-label="Reactions">
                            {message.reactions.map((reaction) => {
                                const reacted = viewerId !== null && reaction.user_ids.includes(viewerId);

                                return (
                                    <li key={reaction.emoji}>
                                        <button
                                            type="button"
                                            onClick={() => onReact(message, reaction.emoji)}
                                            aria-pressed={reacted}
                                            className={cn(
                                                'flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition-colors',
                                                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                reacted
                                                    ? 'border-primary bg-primary/10 text-foreground'
                                                    : 'border-border text-muted-foreground hover:bg-accent',
                                            )}
                                        >
                                            <span aria-hidden="true">{reaction.emoji}</span>
                                            <span>{reaction.count}</span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {readBy.length > 0 && <p className="mt-1 text-xs text-muted-foreground">Read by {readBy.join(', ')}</p>}
                </div>

                {/*
                    Kept in the DOM rather than conditionally mounted so the
                    actions are reachable by keyboard: `focus-within` reveals
                    them for a tabbing user exactly as hover does for a mouse.
                */}
                <div className="flex shrink-0 items-start gap-0.5 opacity-0 transition-opacity group-focus-within/message:opacity-100 group-hover/message:opacity-100">
                    {QUICK_REACTIONS.map((emoji) => (
                        <Button
                            key={emoji}
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            aria-label={`React with ${emoji}`}
                            onClick={() => onReact(message, emoji)}
                        >
                            <span aria-hidden="true">{emoji}</span>
                        </Button>
                    ))}

                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        aria-label="Add a reaction"
                        onClick={() => onOpenEmoji(message)}
                    >
                        <SmilePlus className="size-4" aria-hidden="true" />
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button type="button" size="icon" variant="ghost" className="size-7" aria-label="Message actions">
                                <Ellipsis className="size-4" aria-hidden="true" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onSelect={() => onReply(message)}>
                                <CornerUpLeft className="size-4" aria-hidden="true" />
                                Reply
                            </DropdownMenuItem>
                            {mayEdit && (
                                <DropdownMenuItem onSelect={() => setEditing(true)}>
                                    <Pencil className="size-4" aria-hidden="true" />
                                    Edit
                                </DropdownMenuItem>
                            )}
                            {mayDelete && (
                                <DropdownMenuItem variant="destructive" onSelect={() => onDelete(message)}>
                                    <Trash2 className="size-4" aria-hidden="true" />
                                    Delete
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </article>
    );
}

export function AttachmentHint({ maxKb }: { maxKb: number }) {
    return (
        <p className="flex items-center gap-1 text-xs text-muted-foreground">
            <Paperclip className="size-3" aria-hidden="true" />
            Up to {Math.round(maxKb / 1024)} MB per file
        </p>
    );
}
