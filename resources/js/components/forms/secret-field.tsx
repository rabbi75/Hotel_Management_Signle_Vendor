import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, EyeOff } from 'lucide-react';
import { useId, useState } from 'react';

export interface SecretFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    /** Whether a credential is already stored for this key. */
    isSet: boolean;
    /** The placeholder the server sends in place of a stored secret. */
    mask: string;
    error?: string | undefined;
    description?: string;
    autoComplete?: string;
    disabled?: boolean;
}

/**
 * An encrypted setting. The stored value never reaches the browser, so the input
 * starts empty and an empty input is submitted as "unchanged" — clearing a
 * credential is a deliberate action elsewhere, never an accident of a blank box.
 */
export function SecretField({
    label,
    value,
    onChange,
    isSet,
    mask,
    error,
    description,
    autoComplete = 'off',
    disabled = false,
}: SecretFieldProps) {
    const id = useId();
    const [revealed, setRevealed] = useState(false);

    const descriptionId = `${id}-description`;
    const errorId = `${id}-error`;
    const describedBy = [descriptionId, error ? errorId : null].filter(Boolean).join(' ');

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <Label htmlFor={id}>{label}</Label>
                <Badge variant={isSet ? 'success' : 'outline'}>{isSet ? 'Configured' : 'Not set'}</Badge>
            </div>

            <div className="relative">
                <Input
                    id={id}
                    type={revealed ? 'text' : 'password'}
                    value={value}
                    autoComplete={autoComplete}
                    disabled={disabled}
                    spellCheck={false}
                    className="pr-10 font-mono"
                    placeholder={isSet ? mask : 'Not configured'}
                    aria-invalid={Boolean(error)}
                    aria-describedby={describedBy}
                    onChange={(event) => onChange(event.target.value)}
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    className="absolute top-1/2 right-1 -translate-y-1/2"
                    aria-pressed={revealed}
                    aria-label={revealed ? `Hide ${label}` : `Show ${label}`}
                    disabled={disabled || value === ''}
                    onClick={() => setRevealed((current) => !current)}
                >
                    {revealed ? <EyeOff aria-hidden="true" /> : <Eye aria-hidden="true" />}
                </Button>
            </div>

            <p id={descriptionId} className="text-xs text-muted-foreground">
                {description ?? (isSet ? 'Leave blank to keep the stored value.' : 'No value stored yet.')}
            </p>

            {error && (
                <p id={errorId} className="text-sm font-medium text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
