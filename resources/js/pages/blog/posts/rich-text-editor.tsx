import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Strikethrough,
    Undo2,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect } from 'react';

export interface RichTextEditorProps {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    disabled?: boolean;
    'aria-describedby'?: string | undefined;
    id?: string;
}

/**
 * The TipTap surface for `body_format: html`.
 *
 * Its output is HTML the author composed in their own browser, so it is
 * sanitised server-side by App\Modules\Blog\Services\HtmlSanitizer before it is
 * ever stored or rendered. Nothing this component produces is trusted.
 */
export function RichTextEditor({ value, onChange, placeholder, disabled = false, id, ...aria }: RichTextEditorProps) {
    const editor = useEditor({
        editable: !disabled,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
            }),
            Link.configure({
                openOnClick: false,
                // Mirrors the server allow list, so the editor cannot compose a
                // link the sanitiser will silently strip on save.
                protocols: ['http', 'https', 'mailto', 'tel'],
                HTMLAttributes: { rel: 'noopener noreferrer' },
            }),
            Image.configure({ inline: false }),
            Placeholder.configure({ placeholder: placeholder ?? 'Write something…' }),
        ],
        content: value,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'prose-editor min-h-[24rem] w-full px-4 py-3 outline-none',
                ...(id ? { id } : {}),
                ...(aria['aria-describedby'] ? { 'aria-describedby': aria['aria-describedby'] } : {}),
            },
        },
        onUpdate: ({ editor: instance }) => onChange(instance.getHTML()),
    });

    // Only reset when the incoming value genuinely differs, or every keystroke
    // would round-trip through setContent and collapse the selection.
    useEffect(() => {
        if (editor && !editor.isDestroyed && value !== editor.getHTML()) {
            editor.commands.setContent(value, { emitUpdate: false });
        }
    }, [editor, value]);

    useEffect(() => {
        editor?.setEditable(!disabled);
    }, [editor, disabled]);

    const setLink = useCallback(() => {
        if (!editor) {
            return;
        }

        const previous = String(editor.getAttributes('link').href ?? '');
        const url = window.prompt('Link URL', previous);

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }, [editor]);

    const addImage = useCallback(() => {
        if (!editor) {
            return;
        }

        const src = window.prompt('Image URL');

        if (!src) {
            return;
        }

        const alt = window.prompt('Describe the image for screen readers and image search') ?? '';

        editor.chain().focus().setImage({ src, alt }).run();
    }, [editor]);

    if (!editor) {
        return (
            <div className="min-h-[24rem] animate-pulse rounded-md border border-border bg-muted/40" aria-busy="true">
                <span className="sr-only">Loading the editor</span>
            </div>
        );
    }

    return (
        <div className={cn('overflow-hidden rounded-md border border-border bg-background', disabled && 'opacity-60')}>
            <div
                className="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/40 p-1"
                role="toolbar"
                aria-label="Formatting"
            >
                <ToolButton
                    editor={editor}
                    icon={Bold}
                    label="Bold"
                    active={editor.isActive('bold')}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                />
                <ToolButton
                    editor={editor}
                    icon={Italic}
                    label="Italic"
                    active={editor.isActive('italic')}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                />
                <ToolButton
                    editor={editor}
                    icon={Strikethrough}
                    label="Strikethrough"
                    active={editor.isActive('strike')}
                    onClick={() => editor.chain().focus().toggleStrike().run()}
                />
                <ToolButton
                    editor={editor}
                    icon={Code}
                    label="Inline code"
                    active={editor.isActive('code')}
                    onClick={() => editor.chain().focus().toggleCode().run()}
                />

                <Separator orientation="vertical" className="mx-1 h-6" />

                <ToolButton
                    editor={editor}
                    icon={Heading2}
                    label="Heading 2"
                    active={editor.isActive('heading', { level: 2 })}
                    onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                />
                <ToolButton
                    editor={editor}
                    icon={Heading3}
                    label="Heading 3"
                    active={editor.isActive('heading', { level: 3 })}
                    onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                />
                <ToolButton
                    editor={editor}
                    icon={List}
                    label="Bullet list"
                    active={editor.isActive('bulletList')}
                    onClick={() => editor.chain().focus().toggleBulletList().run()}
                />
                <ToolButton
                    editor={editor}
                    icon={ListOrdered}
                    label="Numbered list"
                    active={editor.isActive('orderedList')}
                    onClick={() => editor.chain().focus().toggleOrderedList().run()}
                />
                <ToolButton
                    editor={editor}
                    icon={Quote}
                    label="Quote"
                    active={editor.isActive('blockquote')}
                    onClick={() => editor.chain().focus().toggleBlockquote().run()}
                />

                <Separator orientation="vertical" className="mx-1 h-6" />

                <ToolButton editor={editor} icon={Link2} label="Add link" active={editor.isActive('link')} onClick={setLink} />
                <ToolButton
                    editor={editor}
                    icon={Link2Off}
                    label="Remove link"
                    active={false}
                    onClick={() => editor.chain().focus().unsetLink().run()}
                />
                <ToolButton editor={editor} icon={ImagePlus} label="Insert image" active={false} onClick={addImage} />

                <Separator orientation="vertical" className="mx-1 h-6" />

                <ToolButton editor={editor} icon={Undo2} label="Undo" active={false} onClick={() => editor.chain().focus().undo().run()} />
                <ToolButton editor={editor} icon={Redo2} label="Redo" active={false} onClick={() => editor.chain().focus().redo().run()} />
            </div>

            <EditorContent editor={editor} />
        </div>
    );
}

function ToolButton({
    icon: Icon,
    label,
    active,
    onClick,
    editor,
}: {
    icon: LucideIcon;
    label: string;
    active: boolean;
    onClick: () => void;
    editor: Editor;
}) {
    return (
        <Button
            type="button"
            variant={active ? 'secondary' : 'ghost'}
            size="icon-sm"
            aria-label={label}
            aria-pressed={active}
            title={label}
            disabled={!editor.isEditable}
            onClick={onClick}
        >
            <Icon className="size-4" aria-hidden="true" />
        </Button>
    );
}
