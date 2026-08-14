import { FormBuilder } from '@/components/forms/form-builder';
import type { FormFieldSchema } from '@/components/forms/schema';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SelectOption, SharedProps } from '@/types';
import type { AppearanceSettings, AppearanceSettingsPageProps } from '@/types/settings';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { DefaultValues } from 'react-hook-form';
import { useSettingsSubmit } from './use-settings-form';

const SIDEBAR_VARIANTS: SelectOption[] = [
    { value: 'sidebar', label: 'Standard', description: 'Flush against the edge of the window.' },
    { value: 'floating', label: 'Floating', description: 'Inset with a shadow.' },
    { value: 'inset', label: 'Inset', description: 'The content area floats instead.' },
];

/**
 * SVG is absent on purpose, matching the server's rules: these files are served
 * from the application's own origin, and an SVG can carry an inline script.
 */
const RASTER = 'image/png,image/jpeg,image/webp';

/**
 * The upload slots, each paired with the setting key it resolves into.
 *
 * The form posts the file under `name`; the server stores the resulting URL
 * under `settingKey` and hands it back on the next render, which is what seeds
 * the preview. The `*_url` values are never posted directly.
 */
const ASSETS = [
    {
        name: 'logo',
        settingKey: 'logo_url',
        label: 'Logo',
        description: 'The wide lockup in the topbars and on the sign-in screens. PNG or WebP, around 64px tall.',
        accept: RASTER,
    },
    {
        name: 'dark_logo',
        settingKey: 'dark_logo_url',
        label: 'Logo (dark theme)',
        description: 'Used in place of the logo on dark backgrounds. Leave empty to use the same one throughout.',
        accept: RASTER,
    },
    {
        name: 'icon',
        settingKey: 'icon_url',
        label: 'Icon',
        description: 'The square mark for tight spots — the console rail, mobile. Square, 512×512.',
        accept: RASTER,
    },
    {
        name: 'favicon',
        settingKey: 'favicon_url',
        label: 'Favicon',
        description: 'The browser tab icon. PNG or ICO, 32×32, up to 512KB.',
        accept: 'image/png,image/x-icon,.ico',
    },
    {
        name: 'landing_logo',
        settingKey: 'landing_logo_url',
        label: 'Landing page logo',
        description: 'The public site and CMS pages. Falls back to the logo above when empty.',
        accept: RASTER,
    },
] as const satisfies ReadonlyArray<{
    name: string;
    settingKey: keyof AppearanceSettings;
    label: string;
    description: string;
    accept?: string;
}>;

type AppearanceForm = Omit<AppearanceSettings, 'logo_url' | 'dark_logo_url' | 'icon_url' | 'favicon_url' | 'landing_logo_url'> &
    Record<(typeof ASSETS)[number]['name'], File | string | null>;

export default function AppearanceSettingsPage({ settings, themes }: AppearanceSettingsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const { submit, submitting, saved } = useSettingsSubmit(route('admin.settings.appearance.update'), { multipart: true });

    const themeFields = useMemo<FormFieldSchema[]>(
        () => [
            {
                name: 'theme',
                label: 'Default colour scheme',
                type: 'radio',
                span: 2,
                required: true,
                options: themes.map((theme) => ({ value: theme.value, label: theme.label })),
                description: 'Applied to anyone who has not chosen their own.',
            },
            { name: 'primary_color', label: 'Brand colour', type: 'color', required: true },
            { name: 'sidebar_variant', label: 'Sidebar style', type: 'select', required: true, options: SIDEBAR_VARIANTS },
        ],
        [themes],
    );

    const assetFields: FormFieldSchema[] = ASSETS.map((asset) => ({
        name: asset.name,
        label: asset.label,
        type: 'image',
        description: asset.description,
        accept: asset.accept,
        maxSizeMb: asset.name === 'favicon' ? 0.5 : 2,
    }));

    const cssField: FormFieldSchema[] = [
        {
            name: 'custom_css',
            label: 'Custom CSS',
            type: 'textarea',
            span: 2,
            rows: 10,
            description: 'Injected on every page. Prefer overriding the design tokens over targeting component classes.',
        },
    ];

    const schema = [...themeFields, ...assetFields, ...cssField];

    // Each asset starts as its stored URL, which `ImageUpload` renders as the
    // current preview; picking a file replaces it with a `File`.
    const defaults = useMemo(() => {
        const values: Record<string, unknown> = { ...settings };

        for (const asset of ASSETS) {
            delete values[asset.settingKey];
            values[asset.name] = storedUrl(settings, asset.settingKey);
        }

        return values as DefaultValues<AppearanceForm>;
    }, [settings]);

    return (
        <AdminSettingsLayout title="Appearance" description="Branding applied across the whole installation.">
            <ErrorSummary errors={errors} handled={schema.map((field) => field.name)} />

            <PanelCard title="Theme and branding" description="These defaults apply to every workspace on this installation.">
                <FormBuilder<AppearanceForm>
                    schema={schema}
                    defaultValues={defaults}
                    errors={errors}
                    submitting={submitting}
                    saved={saved}
                    onSubmit={(values, form) => submit(toPayload(values, settings), () => form.reset(values))}
                />
            </PanelCard>
        </AdminSettingsLayout>
    );
}

/**
 * Only send an asset the operator actually touched.
 *
 * A slot still holding its stored URL is omitted entirely, so saving the theme
 * alone cannot re-upload — or worse, clear — five files. A slot emptied by the
 * remove button becomes an explicit `*_cleared` flag, which is the only thing
 * that tells the server "delete this" apart from "I left it alone".
 */
function toPayload(values: AppearanceForm, settings: AppearanceSettings): Record<string, unknown> {
    const payload: Record<string, unknown> = { ...values };

    for (const asset of ASSETS) {
        const value = values[asset.name];
        delete payload[asset.name];

        if (value instanceof File) {
            payload[asset.name] = value;

            continue;
        }

        if (value === null && storedUrl(settings, asset.settingKey) !== null) {
            payload[`${asset.name}_cleared`] = 1;
        }
    }

    return payload;
}

/**
 * An unset asset comes back from the schema as an empty string, never null —
 * that is what its declared default is. Every caller here wants "no asset", and
 * an empty string would reach `ImageUpload` as a preview `src` of "".
 */
function storedUrl(settings: AppearanceSettings, key: keyof AppearanceSettings): string | null {
    const value = settings[key];

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}
