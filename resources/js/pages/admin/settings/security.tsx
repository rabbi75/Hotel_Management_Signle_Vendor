import { FormBuilder } from '@/components/forms/form-builder';
import type { FormFieldSchema } from '@/components/forms/schema';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SharedProps } from '@/types';
import type { SecuritySettings, SecuritySettingsPageProps } from '@/types/settings';
import { usePage } from '@inertiajs/react';
import type { DefaultValues } from 'react-hook-form';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';
import { linesToList, listToLines, useSettingsSubmit } from './use-settings-form';

/** `allowed_ips` is a list on the wire but a textarea in the UI. */
type SecurityForm = Omit<SecuritySettings, 'allowed_ips'> & { allowed_ips: string };

const FIELDS: FormFieldSchema[] = [
    {
        type: 'section',
        name: 'accounts',
        label: 'Accounts',
        fields: [
            { name: 'email_verification_required', label: 'Require email verification', type: 'switch', span: 2 },
            {
                name: 'two_factor_enforced',
                label: 'Require two-factor authentication',
                type: 'switch',
                span: 2,
                description: 'Members without it are prompted to enrol before they can continue.',
            },
        ],
    },
    {
        type: 'section',
        name: 'passwords',
        label: 'Passwords',
        fields: [
            { name: 'password_min_length', label: 'Minimum length', type: 'number', required: true, min: 8, max: 128 },
            { name: 'password_requires_symbols', label: 'Require a symbol', type: 'switch' },
            {
                name: 'password_expires_days',
                label: 'Expire after (days)',
                type: 'number',
                min: 0,
                max: 3650,
                description: 'Zero disables expiry.',
            },
            { name: 'max_login_attempts', label: 'Failed attempts before lockout', type: 'number', required: true, min: 1, max: 50 },
        ],
    },
    {
        type: 'section',
        name: 'sessions',
        label: 'Sessions and transport',
        fields: [
            { name: 'session_lifetime_minutes', label: 'Session lifetime (minutes)', type: 'number', required: true, min: 5, max: 43200 },
            { name: 'force_https', label: 'Force HTTPS', type: 'switch' },
            {
                name: 'allowed_ips',
                label: 'IP allow-list',
                type: 'textarea',
                span: 2,
                rows: 5,
                placeholder: '198.51.100.4\n203.0.113.0/24',
                description: 'One address or range per line. Leave empty to allow every address.',
            },
        ],
    },
];

const HANDLED = [
    'email_verification_required',
    'two_factor_enforced',
    'password_min_length',
    'password_requires_symbols',
    'password_expires_days',
    'max_login_attempts',
    'session_lifetime_minutes',
    'force_https',
    'allowed_ips',
];

export default function SecuritySettingsPage({ settings }: SecuritySettingsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const { submit, submitting, saved } = useSettingsSubmit(route('admin.settings.security.update'));

    const defaults = { ...settings, allowed_ips: listToLines(settings.allowed_ips) } as DefaultValues<SecurityForm>;

    return (
        <AdminSettingsLayout title="Security" description="Account, password and session policy for this installation.">
            <ErrorSummary errors={errors} handled={HANDLED} />

            <PanelCard title="Policy" description="Applies to every member, including administrators.">
                <FormBuilder<SecurityForm>
                    schema={FIELDS}
                    defaultValues={defaults}
                    errors={errors}
                    submitting={submitting}
                    saved={saved}
                    onSubmit={(values, form) =>
                        submit({ ...values, allowed_ips: linesToList(values.allowed_ips) }, () => form.reset(values))
                    }
                />
            </PanelCard>
        </AdminSettingsLayout>
    );
}
