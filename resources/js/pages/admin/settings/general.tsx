import { FormBuilder } from '@/components/forms/form-builder';
import type { FormFieldSchema } from '@/components/forms/schema';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SharedProps } from '@/types';
import type { GeneralSettings, GeneralSettingsPageProps } from '@/types/settings';
import { usePage } from '@inertiajs/react';
import type { DefaultValues } from 'react-hook-form';
import { ErrorSummary, PanelCard, PanelSkeleton } from '@/components/forms/settings-panel';
import { useSettingsSubmit } from './use-settings-form';

const FIELDS: FormFieldSchema[] = [
    { name: 'app_name', label: 'Application name', type: 'text', required: true, placeholder: 'Acme' },
    {
        name: 'short_name',
        label: 'Short name',
        type: 'text',
        required: true,
        description: 'Used where space is tight, such as the sidebar.',
    },
    { name: 'tagline', label: 'Tagline', type: 'text', span: 2, placeholder: 'Everything your team needs, in one place.' },
    { name: 'support_email', label: 'Support email', type: 'email', required: true, autoComplete: 'email' },
    { name: 'contact_phone', label: 'Contact phone', type: 'text', autoComplete: 'tel' },
    { name: 'address', label: 'Postal address', type: 'textarea', span: 2, rows: 3 },
    {
        name: 'registration_enabled',
        label: 'Public registration',
        type: 'switch',
        span: 2,
        description: 'When off, new accounts can only be created by invitation.',
    },
];

const HANDLED = FIELDS.map((field) => field.name);

export default function GeneralSettingsPage({ settings }: GeneralSettingsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const { submit, submitting, saved } = useSettingsSubmit(route('admin.settings.general.update'));

    return (
        <AdminSettingsLayout title="General" description="Branding and the details shown to everyone who uses this installation.">
            <ErrorSummary errors={errors} handled={HANDLED} />

            <PanelCard title="Application" description="How this installation identifies itself in the interface and in outgoing email.">
                {settings ? (
                    <FormBuilder<GeneralSettings>
                        schema={FIELDS}
                        defaultValues={settings as DefaultValues<GeneralSettings>}
                        errors={errors}
                        submitting={submitting}
                        saved={saved}
                        onSubmit={(values, form) => submit(values, () => form.reset(values))}
                    />
                ) : (
                    <PanelSkeleton rows={5} />
                )}
            </PanelCard>
        </AdminSettingsLayout>
    );
}
