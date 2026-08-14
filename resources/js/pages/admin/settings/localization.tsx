import { FormBuilder } from '@/components/forms/form-builder';
import type { FormFieldSchema } from '@/components/forms/schema';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SelectOption, SharedProps } from '@/types';
import type { LocalizationSettings, LocalizationSettingsPageProps } from '@/types/settings';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { DefaultValues } from 'react-hook-form';
import { ErrorSummary, PanelCard, PanelSkeleton } from '@/components/forms/settings-panel';
import { useSettingsSubmit } from './use-settings-form';

const WEEKDAYS: SelectOption[] = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];

export default function LocalizationSettingsPage({ settings, locales, timezones }: LocalizationSettingsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const { submit, submitting, saved } = useSettingsSubmit(route('admin.settings.localization.update'));

    const localeOptions = useMemo<SelectOption[]>(
        () =>
            Object.entries(locales).map(([code, definition]) => ({
                value: code,
                label: `${definition.native} (${code})`,
                description: definition.name,
            })),
        [locales],
    );

    const timezoneOptions = useMemo<SelectOption[]>(
        () => timezones.map((zone) => ({ value: zone, label: zone.replace(/_/g, ' ') })),
        [timezones],
    );

    const fields = useMemo<FormFieldSchema[]>(
        () => [
            { name: 'default_locale', label: 'Default language', type: 'select', options: localeOptions, required: true },
            { name: 'default_timezone', label: 'Default timezone', type: 'select', options: timezoneOptions, required: true },
            {
                name: 'default_currency',
                label: 'Default currency',
                type: 'text',
                required: true,
                placeholder: 'USD',
                description: 'Three-letter ISO 4217 code.',
            },
            { name: 'week_starts_on', label: 'Week starts on', type: 'select', options: WEEKDAYS, required: true },
            { name: 'date_format', label: 'Date format', type: 'text', required: true, description: 'PHP date() syntax, e.g. d/m/Y.' },
            { name: 'time_format', label: 'Time format', type: 'text', required: true, description: 'PHP date() syntax, e.g. H:i.' },
            {
                name: 'enabled_locales',
                label: 'Available languages',
                type: 'multiselect',
                span: 2,
                options: localeOptions,
                description: 'Only these appear in the language picker.',
            },
        ],
        [localeOptions, timezoneOptions],
    );

    const handled = fields.map((field) => field.name);
    const ready = timezoneOptions.length > 0;

    return (
        <AdminSettingsLayout title="Localization" description="Language, timezone and formatting defaults for new accounts.">
            <ErrorSummary errors={errors} handled={handled} />

            <PanelCard title="Regional defaults" description="A user can override any of these from their own profile.">
                {ready ? (
                    <FormBuilder<LocalizationSettings>
                        schema={fields}
                        defaultValues={settings as DefaultValues<LocalizationSettings>}
                        errors={errors}
                        submitting={submitting}
                        saved={saved}
                        onSubmit={(values, form) => submit(values, () => form.reset(values))}
                    />
                ) : (
                    <PanelSkeleton rows={6} />
                )}
            </PanelCard>
        </AdminSettingsLayout>
    );
}
