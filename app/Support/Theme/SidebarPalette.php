<?php

declare(strict_types=1);

namespace App\Support\Theme;

/**
 * The curated sidebar colour presets.
 *
 * A preset is a complete set of the eight `--sidebar-*` design tokens, authored
 * in OKLCH for both themes, so picking one can never produce an unreadable
 * sidebar — which is exactly why the console offers swatches rather than free
 * hex input.
 *
 * @phpstan-type Tokens array{
 *     sidebar: string,
 *     sidebar-foreground: string,
 *     sidebar-primary: string,
 *     sidebar-primary-foreground: string,
 *     sidebar-accent: string,
 *     sidebar-accent-foreground: string,
 *     sidebar-border: string,
 *     sidebar-ring: string,
 * }
 * @phpstan-type Preset array{label: string, swatch: string, light: Tokens, dark: Tokens}
 */
final class SidebarPalette
{
    public const DEFAULT = 'default';

    /**
     * Every preset, keyed by the value stored in settings.
     *
     * `default` reproduces the tokens declared in resources/css/app.css exactly,
     * so selecting it is indistinguishable from injecting nothing at all.
     *
     * @return array<string, Preset>
     */
    public static function all(): array
    {
        return [
            self::DEFAULT => [
                'label' => 'Default',
                'swatch' => '#2563eb',
                'light' => [
                    'sidebar' => 'oklch(0.985 0 0)',
                    'sidebar-foreground' => 'oklch(0.21 0.006 285.9)',
                    'sidebar-primary' => 'oklch(0.55 0.22 264.4)',
                    'sidebar-primary-foreground' => 'oklch(0.98 0.003 247.9)',
                    'sidebar-accent' => 'oklch(0.967 0.001 286.4)',
                    'sidebar-accent-foreground' => 'oklch(0.21 0.006 285.9)',
                    'sidebar-border' => 'oklch(0.92 0.004 286.3)',
                    'sidebar-ring' => 'oklch(0.55 0.22 264.4)',
                ],
                'dark' => [
                    'sidebar' => 'oklch(0.19 0.006 285.9)',
                    'sidebar-foreground' => 'oklch(0.96 0.002 286.3)',
                    'sidebar-primary' => 'oklch(0.66 0.19 264.4)',
                    'sidebar-primary-foreground' => 'oklch(0.16 0.005 285.9)',
                    'sidebar-accent' => 'oklch(0.27 0.007 286)',
                    'sidebar-accent-foreground' => 'oklch(0.96 0.002 286.3)',
                    'sidebar-border' => 'oklch(1 0 0 / 10%)',
                    'sidebar-ring' => 'oklch(0.66 0.19 264.4)',
                ],
            ],

            'ocean' => self::tinted('Ocean', '#0ea5e9', 237.3),
            'violet' => self::tinted('Violet', '#7c3aed', 293.5),
            'emerald' => self::tinted('Emerald', '#059669', 162.5),
            'rose' => self::tinted('Rose', '#e11d48', 16.4),
            'amber' => self::tinted('Amber', '#d97706', 70.1),
            'teal' => self::tinted('Teal', '#0d9488', 197.1),

            // The one deliberately dark-in-both-themes preset: a slate rail that
            // stays dark even while the rest of the page is light.
            'midnight' => [
                'label' => 'Midnight',
                'swatch' => '#1e293b',
                'light' => [
                    'sidebar' => 'oklch(0.26 0.017 264.4)',
                    'sidebar-foreground' => 'oklch(0.93 0.006 264.4)',
                    'sidebar-primary' => 'oklch(0.72 0.15 264.4)',
                    'sidebar-primary-foreground' => 'oklch(0.2 0.014 264.4)',
                    'sidebar-accent' => 'oklch(0.33 0.021 264.4)',
                    'sidebar-accent-foreground' => 'oklch(0.97 0.004 264.4)',
                    'sidebar-border' => 'oklch(1 0 0 / 12%)',
                    'sidebar-ring' => 'oklch(0.72 0.15 264.4)',
                ],
                'dark' => [
                    'sidebar' => 'oklch(0.2 0.014 264.4)',
                    'sidebar-foreground' => 'oklch(0.95 0.005 264.4)',
                    'sidebar-primary' => 'oklch(0.72 0.15 264.4)',
                    'sidebar-primary-foreground' => 'oklch(0.17 0.012 264.4)',
                    'sidebar-accent' => 'oklch(0.28 0.019 264.4)',
                    'sidebar-accent-foreground' => 'oklch(0.97 0.004 264.4)',
                    'sidebar-border' => 'oklch(1 0 0 / 10%)',
                    'sidebar-ring' => 'oklch(0.72 0.15 264.4)',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * The client-side view: enough to render a swatch grid and a live preview
     * without the browser knowing anything about how the CSS is assembled.
     *
     * @return list<array{value: string, label: string, swatch: string, light: array<string, string>, dark: array<string, string>}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $value => $preset) {
            $options[] = [
                'value' => $value,
                'label' => $preset['label'],
                'swatch' => $preset['swatch'],
                'light' => $preset['light'],
                'dark' => $preset['dark'],
            ];
        }

        return $options;
    }

    /**
     * The stylesheet that overrides the sidebar tokens for one preset.
     *
     * Selectors are deliberately doubled (`:root:root`, `.dark:root`). In dev
     * Vite injects app.css at runtime, *after* anything static in <head>, so an
     * equal-specificity override would lose the cascade by source order. The
     * doubled selector wins on specificity instead, which holds in both dev and
     * a built bundle.
     */
    public static function css(string $key): string
    {
        $preset = self::all()[$key] ?? null;

        if ($preset === null || $key === self::DEFAULT) {
            return '';
        }

        return ':root:root{'.self::declarations($preset['light']).'}'
            .'.dark:root{'.self::declarations($preset['dark']).'}';
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private static function declarations(array $tokens): string
    {
        $out = '';

        foreach ($tokens as $token => $value) {
            $out .= "--{$token}:{$value};";
        }

        return $out;
    }

    /**
     * A preset built around a single hue.
     *
     * Light keeps a near-white rail and carries the hue only in the accent and
     * active states; dark tints the rail itself. Lightness and chroma are held
     * constant across every hue so all the tinted presets read as one family.
     *
     * @return Preset
     */
    private static function tinted(string $label, string $swatch, float $hue): array
    {
        return [
            'label' => $label,
            'swatch' => $swatch,
            'light' => [
                'sidebar' => "oklch(0.985 0.004 {$hue})",
                'sidebar-foreground' => "oklch(0.25 0.02 {$hue})",
                'sidebar-primary' => "oklch(0.55 0.16 {$hue})",
                'sidebar-primary-foreground' => 'oklch(0.99 0.002 247.9)',
                'sidebar-accent' => "oklch(0.94 0.04 {$hue})",
                'sidebar-accent-foreground' => "oklch(0.38 0.13 {$hue})",
                'sidebar-border' => "oklch(0.9 0.02 {$hue})",
                'sidebar-ring' => "oklch(0.55 0.16 {$hue})",
            ],
            'dark' => [
                'sidebar' => "oklch(0.21 0.03 {$hue})",
                'sidebar-foreground' => "oklch(0.95 0.01 {$hue})",
                'sidebar-primary' => "oklch(0.72 0.14 {$hue})",
                'sidebar-primary-foreground' => "oklch(0.18 0.025 {$hue})",
                'sidebar-accent' => "oklch(0.31 0.06 {$hue})",
                'sidebar-accent-foreground' => "oklch(0.96 0.01 {$hue})",
                'sidebar-border' => 'oklch(1 0 0 / 12%)',
                'sidebar-ring' => "oklch(0.72 0.14 {$hue})",
            ],
        ];
    }
}
