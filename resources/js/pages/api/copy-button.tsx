import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface CopyButtonProps {
    value: string;
    label?: string;
    /** Announced after a successful copy. */
    successMessage?: string;
    size?: 'sm' | 'default' | 'icon-sm';
    variant?: 'default' | 'secondary' | 'outline' | 'ghost';
    className?: string;
    disabled?: boolean;
}

/**
 * Copies a value to the clipboard and confirms it in place.
 *
 * `navigator.clipboard` is unavailable over plain HTTP and inside some embedded
 * browsers, so failure is reported rather than swallowed — a silent no-op on a
 * value the user cannot see again is the worst possible outcome here.
 */
export function CopyButton({
    value,
    label = 'Copy',
    successMessage = 'Copied to the clipboard.',
    size = 'sm',
    variant = 'secondary',
    className,
    disabled = false,
}: CopyButtonProps) {
    const [copied, setCopied] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    async function copy(): Promise<void> {
        try {
            await navigator.clipboard.writeText(value);

            setCopied(true);
            toast.success(successMessage);

            timer.current = setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('Copying failed. Select the value and copy it manually.');
        }
    }

    const isIcon = size === 'icon-sm';

    return (
        <Button
            type="button"
            size={size}
            variant={variant}
            className={className}
            disabled={disabled}
            aria-label={isIcon ? label : undefined}
            onClick={() => void copy()}
        >
            {copied ? <Check className="size-4" aria-hidden="true" /> : <Copy className="size-4" aria-hidden="true" />}
            {!isIcon && <span>{copied ? 'Copied' : label}</span>}
        </Button>
    );
}
