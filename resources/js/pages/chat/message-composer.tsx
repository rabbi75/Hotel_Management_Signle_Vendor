import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { ChatMessage } from '@/types/chat';
import { CornerUpLeft, FileText, Paperclip, SendHorizontal, Smile, X } from 'lucide-react';
import { lazy, Suspense, useId, useRef, useState, type ChangeEvent, type FormEvent, type KeyboardEvent } from 'react';

/** ~400 kB of emoji data; only paid for when someone opens the picker. */
const EmojiPicker = lazy(() => import('emoji-picker-react'));

export interface MessageComposerProps {
    maxLength: number;
    maxAttachmentKb: number;
    canUpload: boolean;
    replyTo: ChatMessage | null;
    onCancelReply: () => void;
    sending: boolean;
    error: string | null;
    onSend: (body: string, files: File[]) => void;
    onTyping: () => void;
}

export function MessageComposer({
    maxLength,
    maxAttachmentKb,
    canUpload,
    replyTo,
    onCancelReply,
    sending,
    error,
    onSend,
    onTyping,
}: MessageComposerProps) {
    const [body, setBody] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [emojiOpen, setEmojiOpen] = useState(false);
    const [sizeError, setSizeError] = useState<string | null>(null);

    const fileInput = useRef<HTMLInputElement | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);
    const fieldId = useId();
    const errorId = `${fieldId}-error`;

    const tooLong = body.length > maxLength;
    const canSend = !sending && !tooLong && (body.trim() !== '' || files.length > 0);
    const message = sizeError ?? error;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!canSend) {
            return;
        }

        onSend(body.trim(), files);
        setBody('');
        setFiles([]);
        setSizeError(null);
        textareaRef.current?.focus();
    }

    function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>): void {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    }

    function pickFiles(event: ChangeEvent<HTMLInputElement>): void {
        const picked = [...(event.target.files ?? [])];
        const limit = maxAttachmentKb * 1024;
        const oversized = picked.find((file) => file.size > limit);

        if (oversized) {
            setSizeError(`"${oversized.name}" is larger than the ${Math.round(maxAttachmentKb / 1024)} MB limit.`);
            event.target.value = '';

            return;
        }

        setSizeError(null);
        setFiles((current) => [...current, ...picked]);
        event.target.value = '';
    }

    return (
        <form onSubmit={submit} className="border-t border-border p-3">
            {replyTo && (
                <div className="mb-2 flex items-center gap-2 rounded-md bg-muted px-2 py-1.5 text-xs">
                    <CornerUpLeft className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span className="min-w-0 flex-1 truncate">
                        Replying to <span className="font-medium">{replyTo.author?.name ?? 'a message'}</span>: {replyTo.body}
                    </span>
                    <Button type="button" size="icon" variant="ghost" className="size-6" onClick={onCancelReply} aria-label="Cancel reply">
                        <X className="size-3.5" aria-hidden="true" />
                    </Button>
                </div>
            )}

            {files.length > 0 && (
                <ul className="mb-2 flex flex-wrap gap-2" aria-label="Pending attachments">
                    {files.map((file, index) => (
                        <li
                            key={`${file.name}-${index}`}
                            className="flex items-center gap-2 rounded-md border border-border px-2 py-1 text-xs"
                        >
                            {file.type.startsWith('image/') ? (
                                <img src={URL.createObjectURL(file)} alt="" className="size-8 rounded object-cover" />
                            ) : (
                                <FileText className="size-3.5 text-muted-foreground" aria-hidden="true" />
                            )}
                            <span className="max-w-40 truncate">{file.name}</span>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-5"
                                aria-label={`Remove ${file.name}`}
                                onClick={() => setFiles((current) => current.filter((_, position) => position !== index))}
                            >
                                <X className="size-3" aria-hidden="true" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex items-end gap-2">
                <div className="min-w-0 flex-1">
                    <label htmlFor={fieldId} className="sr-only">
                        Write a message
                    </label>
                    <Textarea
                        id={fieldId}
                        ref={textareaRef}
                        rows={1}
                        value={body}
                        placeholder="Write a message…"
                        aria-invalid={tooLong || message ? true : undefined}
                        aria-describedby={message || tooLong ? errorId : undefined}
                        className="max-h-40 min-h-10 resize-none"
                        onChange={(event) => {
                            setBody(event.target.value);
                            onTyping();
                        }}
                        onKeyDown={onKeyDown}
                    />
                </div>

                <Popover open={emojiOpen} onOpenChange={setEmojiOpen}>
                    <PopoverTrigger asChild>
                        <Button type="button" size="icon" variant="ghost" aria-label="Insert an emoji">
                            <Smile className="size-4" aria-hidden="true" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent align="end" className="w-auto border-none p-0">
                        <Suspense fallback={<div className="p-4 text-sm text-muted-foreground">Loading emoji…</div>}>
                            <EmojiPicker
                                onEmojiClick={(emoji) => {
                                    setBody((current) => current + emoji.emoji);
                                    setEmojiOpen(false);
                                    textareaRef.current?.focus();
                                }}
                                lazyLoadEmojis
                            />
                        </Suspense>
                    </PopoverContent>
                </Popover>

                {canUpload && (
                    <>
                        <input
                            ref={fileInput}
                            type="file"
                            multiple
                            className="sr-only"
                            onChange={pickFiles}
                            aria-hidden="true"
                            tabIndex={-1}
                        />
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            aria-label="Attach a file"
                            onClick={() => fileInput.current?.click()}
                        >
                            <Paperclip className="size-4" aria-hidden="true" />
                        </Button>
                    </>
                )}

                <Button type="submit" size="icon" disabled={!canSend} aria-label="Send message">
                    <SendHorizontal className="size-4" aria-hidden="true" />
                </Button>
            </div>

            <div className="mt-1.5 flex items-center justify-between gap-3">
                <p
                    id={errorId}
                    className={cn('text-xs', message || tooLong ? 'text-destructive' : 'sr-only')}
                    role={message ? 'alert' : undefined}
                >
                    {message ?? (tooLong ? `Messages are limited to ${maxLength} characters.` : 'No errors')}
                </p>
                <p className={cn('shrink-0 text-xs', tooLong ? 'text-destructive' : 'text-muted-foreground')}>
                    {body.length}/{maxLength}
                </p>
            </div>
        </form>
    );
}
