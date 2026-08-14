import { cn } from '@/lib/utils';
import type { BlockData } from '@/types/cms';
import type { ReactNode } from 'react';
import { str } from './blocks/support';

/*
|------------------------------------------------------------------------------
| The section wrapper
|------------------------------------------------------------------------------
|
| Every block renders inside one of these, reading the three layout fields that
| BlockSchema::sectionFields() adds to all of them. Keeping the background,
| rhythm and measure here — rather than in each renderer — is what lets an
| author build a page that reads as designed instead of as a stack of identical
| bands, and it means a new block type inherits the whole system for free.
|
| This file deliberately sits outside `blocks/`: that directory is glob-scanned
| by blocks/registry.ts, where every .tsx file with a default export becomes a
| block type.
|
*/

const BACKGROUNDS: Record<string, string> = {
    default: 'bg-background text-foreground',
    muted: 'bg-muted/40 text-foreground',
    accent: 'bg-primary/5 text-foreground',
    gradient: 'bg-background text-foreground',
    dark: 'bg-foreground text-background',
};

const PADDING: Record<string, string> = {
    none: 'py-0',
    compact: 'py-10 sm:py-12',
    normal: 'py-16 sm:py-20',
    spacious: 'py-24 sm:py-32',
};

const WIDTHS: Record<string, string> = {
    narrow: 'max-w-3xl',
    normal: 'max-w-5xl',
    wide: 'max-w-6xl',
    full: 'max-w-none',
};

export interface SectionProps {
    data: BlockData;
    children: ReactNode;
    className?: string;
    /** Extra classes for the inner container, e.g. text alignment. */
    innerClassName?: string;
    'aria-labelledby'?: string;
}

export function Section({ data, children, className, innerClassName, ...aria }: SectionProps) {
    const background = str(data, 'section_background', 'default');
    const padding = str(data, 'section_padding', 'normal');
    const width = str(data, 'section_width', 'normal');

    return (
        <section
            className={cn('relative isolate overflow-hidden px-4 sm:px-6', BACKGROUNDS[background] ?? BACKGROUNDS.default, PADDING[padding] ?? PADDING.normal, className)}
            {...aria}
        >
            {background === 'gradient' && <SectionGlow />}

            <div className={cn('relative mx-auto w-full', WIDTHS[width] ?? WIDTHS.normal, innerClassName)}>{children}</div>
        </section>
    );
}

/**
 * The soft colour wash behind a gradient section. Purely decorative, so it is
 * hidden from assistive technology and never intercepts a pointer.
 */
export function SectionGlow() {
    return (
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10">
            <div className="absolute -top-40 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div className="absolute -right-32 bottom-0 size-[26rem] rounded-full bg-chart-5/10 blur-3xl" />
            <div className="absolute -left-32 bottom-10 size-[26rem] rounded-full bg-chart-2/10 blur-3xl" />
        </div>
    );
}

/**
 * A centred heading + supporting copy, used by most blocks. One component so
 * the type scale and spacing stay identical across the whole page.
 */
export function SectionHeading({
    id,
    heading,
    subheading,
    align = 'center',
    className,
}: {
    id?: string;
    heading: string;
    subheading?: string;
    align?: 'left' | 'center';
    className?: string;
}) {
    if (!heading && !subheading) {
        return null;
    }

    return (
        <div className={cn('max-w-2xl', align === 'center' ? 'mx-auto text-center' : 'text-left', className)}>
            {heading && (
                <h2 id={id} className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    {heading}
                </h2>
            )}
            {subheading && <p className="mt-4 text-lg text-pretty opacity-70">{subheading}</p>}
        </div>
    );
}
