import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useId, useState } from 'react';

export interface ImageUploadProps {
    /** A staged `File`, an existing URL, or nothing. */
    value: File | string | null;
    onChange: (value: File | null) => void;
    accept?: string;
    maxSizeMb?: number;
    /** Used for the accessible name of the preview and the remove button. */
    label?: string;
    shape?: 'square' | 'circle';
    invalid?: boolean;
    describedBy?: string | undefined;
    disabled?: boolean;
    className?: string;
}

/**
 * Avatar and logo picker. The preview is a fixed square filled with
 * `object-cover`, so no cropping UI is needed to get a predictable result.
 */
export function ImageUpload({
    value,
    onChange,
    accept = 'image/*',
    maxSizeMb = 4,
    label = 'Image',
    shape = 'square',
    invalid = false,
    describedBy,
    disabled = false,
    className,
}: ImageUploadProps) {
    const inputId = useId();
    const [error, setError] = useState<string | null>(null);
    const [preview, setPreview] = useState<string | null>(typeof value === 'string' ? value : null);

    useEffect(() => {
        if (typeof value === 'string') {
            setPreview(value);

            return;
        }

        if (!value) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(value);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [value]);

    function pick(file: File | undefined): void {
        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            setError('Choose an image file.');

            return;
        }

        if (file.size > maxSizeMb * 1024 * 1024) {
            setError(`Images must be under ${maxSizeMb} MB.`);

            return;
        }

        setError(null);
        onChange(file);
    }

    const errorId = `${inputId}-error`;
    const described = [describedBy, error ? errorId : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex items-center gap-4">
                <span
                    className={cn(
                        'flex size-20 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted',
                        shape === 'circle' ? 'rounded-full' : 'rounded-lg',
                        (invalid || error) && 'border-destructive',
                    )}
                >
                    {preview ? (
                        <img src={preview} alt={`${label} preview`} className="size-full object-cover" />
                    ) : (
                        <ImagePlus className="size-6 text-muted-foreground" aria-hidden="true" />
                    )}
                </span>

                <div className="space-y-2">
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" size="sm" disabled={disabled} asChild>
                            <label htmlFor={inputId} className="cursor-pointer">
                                {preview ? 'Replace' : 'Upload'}
                            </label>
                        </Button>

                        {preview && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={disabled}
                                onClick={() => {
                                    setError(null);
                                    onChange(null);
                                }}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Remove
                            </Button>
                        )}
                    </div>

                    <p className="text-xs text-muted-foreground">PNG, JPG or SVG. Up to {maxSizeMb} MB.</p>
                </div>
            </div>

            <input
                id={inputId}
                type="file"
                className="sr-only"
                accept={accept}
                disabled={disabled}
                aria-invalid={invalid || Boolean(error)}
                aria-describedby={described}
                onChange={(event) => {
                    pick(event.target.files?.[0]);
                    event.target.value = '';
                }}
            />

            {error && (
                <p id={errorId} className="text-sm font-medium text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
