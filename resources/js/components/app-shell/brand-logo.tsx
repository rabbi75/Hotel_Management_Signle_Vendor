import { cn } from '@/lib/utils';
import type { Branding, SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Sparkles, type LucideIcon } from 'lucide-react';

/**
 * `full` is the wide lockup used in the topbars and on the auth screens, `icon`
 * the square mark for tight spots, `landing` the public site's own lockup —
 * which is often a different, more generous treatment than the one the app
 * chrome wants.
 */
export type BrandLogoVariant = 'full' | 'icon' | 'landing';

export interface BrandLogoProps {
    variant?: BrandLogoVariant;
    /**
     * Render the wordmark next to the image. Defaults to "only when there is no
     * image", because an uploaded logo almost always contains the name already
     * and printing it twice reads as a mistake.
     */
    showName?: boolean;
    /** Overrides the wordmark, e.g. the console's "Platform Console". */
    name?: string;
    /** The lucide mark drawn when nothing has been uploaded. */
    fallbackIcon?: LucideIcon;
    className?: string;
    /** Sizing for the uploaded image; height-only, so any aspect ratio survives. */
    imageClassName?: string;
    /** Sizing for the fallback mark's tile. */
    markClassName?: string;
    nameClassName?: string;
}

/**
 * The one place the installation's brand is drawn.
 *
 * Light and dark are resolved in CSS rather than by reading the theme in JS: a
 * `useTheme()` branch renders the light logo for the first frame after a hard
 * reload and then swaps it, which is exactly the flash `HandleAppearance`
 * exists to prevent everywhere else.
 *
 * With nothing uploaded, every surface falls back to the mark-and-wordmark it
 * rendered before branding existed, so a fresh install looks untouched.
 */
export function BrandLogo({
    variant = 'full',
    showName,
    name,
    fallbackIcon: Fallback = Sparkles,
    className,
    imageClassName,
    markClassName,
    nameClassName,
}: BrandLogoProps) {
    const { branding } = usePage<SharedProps>().props;

    const { light, dark } = resolveSources(branding, variant);
    const label = name ?? branding.name;
    const withName = showName ?? light === null;

    const imageClasses = cn('w-auto object-contain', variant === 'icon' ? 'size-8' : 'h-8 max-w-[12rem]', imageClassName);

    return (
        <span className={cn('flex items-center gap-2', className)}>
            {light === null ? (
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm',
                        markClassName,
                    )}
                >
                    <Fallback className="size-4" aria-hidden="true" />
                </span>
            ) : (
                <>
                    <img src={light} alt="" className={cn(imageClasses, dark !== light && 'dark:hidden')} />
                    {dark !== light && <img src={dark} alt="" className={cn(imageClasses, 'hidden dark:block')} />}
                </>
            )}

            {withName && <span className={cn('truncate font-semibold tracking-tight', nameClassName)}>{label}</span>}
        </span>
    );
}

/**
 * The asset each variant prefers, and what it settles for.
 *
 * Every chain ends at `icon` (or at `logo` for the icon variant) so uploading a
 * single asset is enough to brand the whole installation; only an operator who
 * wants a different treatment per surface has to upload more than one.
 */
function resolveSources(branding: Branding, variant: BrandLogoVariant): { light: null; dark: null } | { light: string; dark: string } {
    const light =
        variant === 'icon'
            ? (branding.icon ?? branding.logo)
            : variant === 'landing'
              ? (branding.landing_logo ?? branding.logo ?? branding.icon)
              : (branding.logo ?? branding.icon);

    if (light === null) {
        return { light: null, dark: null };
    }

    // The dark override only applies where a light logo was in play; the square
    // mark is expected to work on either background.
    const dark = variant === 'icon' ? light : (branding.dark_logo ?? light);

    return { light, dark };
}
