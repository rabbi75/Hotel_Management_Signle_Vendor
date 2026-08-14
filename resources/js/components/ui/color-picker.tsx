import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useRef } from 'react';

const CHART_SWATCHES = [
    { name: 'Chart 1', className: 'bg-chart-1' },
    { name: 'Chart 2', className: 'bg-chart-2' },
    { name: 'Chart 3', className: 'bg-chart-3' },
    { name: 'Chart 4', className: 'bg-chart-4' },
    { name: 'Chart 5', className: 'bg-chart-5' },
    { name: 'Chart 6', className: 'bg-chart-6' },
] as const;

/** Browsers resolve `oklch()` custom properties to `rgb()` when read back via getComputedStyle, so swatches can be converted to hex without a colour-math dependency. */
function rgbStringToHex(rgb: string): string | null {
    const match = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(rgb);

    if (!match) {
        return null;
    }

    const [, r, g, b] = match;
    const toHex = (channel: string) => Number(channel).toString(16).padStart(2, '0');

    return `#${toHex(r ?? '0')}${toHex(g ?? '0')}${toHex(b ?? '0')}`;
}

export interface ColorPickerProps {
    value: string;
    onChange: (hex: string) => void;
    disabled?: boolean;
    className?: string;
}

/** A swatch grid over the chart tokens, backed by a native colour input and a hex text field for anything off-palette. */
export function ColorPicker({ value, onChange, disabled, className }: ColorPickerProps) {
    const swatchRefs = useRef<Record<string, HTMLButtonElement | null>>({});

    function handleSwatchClick(name: string): void {
        const el = swatchRefs.current[name];
        const hex = el ? rgbStringToHex(getComputedStyle(el).backgroundColor) : null;

        if (hex) {
            onChange(hex);
        }
    }

    return (
        <div className={cn('flex flex-col gap-3', className)}>
            <div className="grid grid-cols-6 gap-2">
                {CHART_SWATCHES.map((swatch) => (
                    <button
                        key={swatch.name}
                        ref={(el) => {
                            swatchRefs.current[swatch.name] = el;
                        }}
                        type="button"
                        disabled={disabled}
                        aria-label={swatch.name}
                        onClick={() => handleSwatchClick(swatch.name)}
                        className={cn(
                            'size-7 rounded-md border border-border shadow-xs transition-transform outline-none hover:scale-105',
                            'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                            'disabled:pointer-events-none disabled:opacity-50',
                            swatch.className,
                        )}
                    />
                ))}
            </div>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    value={/^#[0-9a-fA-F]{6}$/.test(value) ? value : '#000000'}
                    onChange={(event) => onChange(event.target.value)}
                    disabled={disabled}
                    aria-label="Pick a custom color"
                    className="size-9 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-1 disabled:pointer-events-none disabled:opacity-50"
                />
                <Input
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    disabled={disabled}
                    placeholder="#000000"
                    spellCheck={false}
                    className="font-mono"
                    aria-label="Hex color"
                />
            </div>
        </div>
    );
}
