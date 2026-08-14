import { FormBuilder } from '@/components/forms/form-builder';
import type { FormFieldSchema } from '@/components/forms/schema';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SharedProps } from '@/types';
import type { StorageSettings, StorageSettingsPageProps } from '@/types/settings';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { DefaultValues } from 'react-hook-form';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';
import { untouchedSecret, useSettingsSubmit, type SettingsPayload } from './use-settings-form';

const S3_ONLY = { field: 'disk', equals: 's3' } as const;
const R2_ONLY = { field: 'disk', equals: 'r2' } as const;

/** Encrypted keys: an empty input means "keep what is stored". */
const SECRET_FIELDS = ['s3_key', 's3_secret', 'r2_access_key_id', 'r2_secret_access_key'] as const;

export default function StorageSettingsPage({ settings, secrets, drivers }: StorageSettingsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const { submit, submitting, saved } = useSettingsSubmit(route('admin.settings.storage.update'));

    function secretDescription(field: (typeof SECRET_FIELDS)[number]): string {
        return secrets[field] === true ? 'A value is stored. Leave blank to keep it.' : 'No value stored yet.';
    }

    const fields = useMemo<FormFieldSchema[]>(
        () => [
            {
                name: 'disk',
                label: 'Storage driver',
                type: 'select',
                required: true,
                options: drivers.map((driver) => ({ value: driver.value, label: driver.label })),
                description: 'Where uploaded files are written.',
            },
            {
                name: 'max_upload_kb',
                label: 'Maximum upload size (KB)',
                type: 'number',
                required: true,
                min: 64,
                max: 1048576,
            },
            {
                type: 'section',
                name: 's3',
                label: 'Amazon S3',
                description: 'Credentials for the bucket uploads are written to.',
                showWhen: S3_ONLY,
                fields: [
                    {
                        name: 's3_key',
                        label: 'Access key ID',
                        type: 'password',
                        autoComplete: 'off',
                        description: secretDescription('s3_key'),
                    },
                    {
                        name: 's3_secret',
                        label: 'Secret access key',
                        type: 'password',
                        autoComplete: 'off',
                        description: secretDescription('s3_secret'),
                    },
                    { name: 's3_region', label: 'Region', type: 'text', placeholder: 'eu-west-1' },
                    { name: 's3_bucket', label: 'Bucket', type: 'text' },
                    { name: 's3_endpoint', label: 'Endpoint', type: 'text', placeholder: 'https://s3.eu-west-1.amazonaws.com' },
                    {
                        name: 's3_use_path_style',
                        label: 'Use path-style endpoints',
                        type: 'switch',
                        description: 'Required by most S3-compatible services.',
                    },
                ],
            },
            {
                type: 'section',
                name: 'r2',
                label: 'Cloudflare R2',
                description: 'R2 speaks the S3 protocol but keeps its own credentials here.',
                showWhen: R2_ONLY,
                fields: [
                    { name: 'r2_account_id', label: 'Account ID', type: 'text' },
                    {
                        name: 'r2_access_key_id',
                        label: 'Access key ID',
                        type: 'password',
                        autoComplete: 'off',
                        description: secretDescription('r2_access_key_id'),
                    },
                    {
                        name: 'r2_secret_access_key',
                        label: 'Secret access key',
                        type: 'password',
                        autoComplete: 'off',
                        description: secretDescription('r2_secret_access_key'),
                    },
                    { name: 'r2_bucket', label: 'Bucket', type: 'text' },
                    { name: 'r2_public_url', label: 'Public URL', type: 'text', placeholder: 'https://files.example.com' },
                ],
            },
        ],
        // `secretDescription` only reads `secrets`, which is what actually changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [drivers, secrets],
    );

    const defaults = {
        ...settings,
        s3_key: '',
        s3_secret: '',
        r2_access_key_id: '',
        r2_secret_access_key: '',
    } as DefaultValues<StorageSettings>;

    const handled = [
        'disk',
        'max_upload_kb',
        's3_key',
        's3_secret',
        's3_region',
        's3_bucket',
        's3_endpoint',
        's3_use_path_style',
        'r2_account_id',
        'r2_access_key_id',
        'r2_secret_access_key',
        'r2_bucket',
        'r2_public_url',
    ];

    return (
        <AdminSettingsLayout title="Storage" description="Where uploaded files are kept.">
            <ErrorSummary errors={errors} handled={handled} />

            <PanelCard title="File storage" description="Only the fields for the selected driver are sent.">
                <FormBuilder<StorageSettings>
                    schema={fields}
                    defaultValues={defaults}
                    errors={errors}
                    submitting={submitting}
                    saved={saved}
                    onSubmit={(values, form) => {
                        const payload: SettingsPayload = { ...values };

                        for (const field of SECRET_FIELDS) {
                            payload[field] = untouchedSecret(values[field]);
                        }

                        submit(payload, () =>
                            form.reset({ ...values, s3_key: '', s3_secret: '', r2_access_key_id: '', r2_secret_access_key: '' }),
                        );
                    }}
                />
            </PanelCard>
        </AdminSettingsLayout>
    );
}
