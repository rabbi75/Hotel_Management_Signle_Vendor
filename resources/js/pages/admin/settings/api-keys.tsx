import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { EmptyState } from '@/components/ui/empty-state';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SharedProps } from '@/types';
import type { ApiKeySettingsPageProps } from '@/types/settings';
import { usePage } from '@inertiajs/react';
import { Info, KeyRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';
import { SecretField } from '@/components/forms/secret-field';
import { untouchedSecret, useSettingsSubmit, type SettingsPayload } from './use-settings-form';

interface ProviderGroup {
    label: string;
    description: string;
    fields: { name: string; label: string }[];
}

const PROVIDERS: Record<string, ProviderGroup> = {
    pusher: {
        label: 'Pusher',
        description: 'Powers real-time broadcasting.',
        fields: [
            { name: 'pusher_app_id', label: 'App ID' },
            { name: 'pusher_key', label: 'Key' },
            { name: 'pusher_secret', label: 'Secret' },
            { name: 'pusher_cluster', label: 'Cluster' },
        ],
    },
    google: {
        label: 'Google',
        description: 'Social sign-in and Maps.',
        fields: [
            { name: 'google_client_id', label: 'OAuth client ID' },
            { name: 'google_client_secret', label: 'OAuth client secret' },
            { name: 'google_maps_key', label: 'Maps API key' },
        ],
    },
    facebook: {
        label: 'Facebook',
        description: 'Social sign-in.',
        fields: [
            { name: 'facebook_app_id', label: 'App ID' },
            { name: 'facebook_app_secret', label: 'App secret' },
        ],
    },
    openai: {
        label: 'OpenAI',
        description: 'Assistive features that call a language model.',
        fields: [
            { name: 'openai_api_key', label: 'API key' },
            { name: 'openai_organization', label: 'Organization ID' },
        ],
    },
};

function blankDraft(providers: string[]): Record<string, string> {
    const draft: Record<string, string> = {};

    for (const provider of providers) {
        for (const field of PROVIDERS[provider]?.fields ?? []) {
            draft[field.name] = '';
        }
    }

    return draft;
}

export default function ApiKeySettingsPage({ secrets, providers, mask }: ApiKeySettingsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const { submit, submitting, saved } = useSettingsSubmit(route('admin.settings.api_keys.update'));

    const known = providers.filter((provider) => PROVIDERS[provider] !== undefined);
    const [draft, setDraft] = useState<Record<string, string>>(() => blankDraft(known));

    const handled = Object.keys(draft);
    const dirty = Object.values(draft).some((value) => value !== '');

    function onSubmit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const payload: SettingsPayload = {};

        for (const [field, value] of Object.entries(draft)) {
            payload[field] = untouchedSecret(value);
        }

        submit(payload, () => setDraft(blankDraft(known)));
    }

    if (known.length === 0) {
        return (
            <AdminSettingsLayout title="API keys" description="Credentials for the third-party services this installation talks to.">
                <EmptyState
                    icon={KeyRound}
                    title="No integrations available"
                    description="This installation does not expose any third-party credentials."
                />
            </AdminSettingsLayout>
        );
    }

    return (
        <AdminSettingsLayout title="API keys" description="Credentials for the third-party services this installation talks to.">
            <ErrorSummary errors={errors} handled={handled} />

            <Alert className="mb-6">
                <Info aria-hidden="true" />
                <AlertDescription>
                    Stored credentials are encrypted and never sent back to the browser. A blank field leaves the stored value untouched.
                </AlertDescription>
            </Alert>

            <form onSubmit={onSubmit} noValidate className="space-y-6">
                {known.map((provider) => {
                    const group = PROVIDERS[provider];

                    if (!group) {
                        return null;
                    }

                    return (
                        <PanelCard key={provider} title={group.label} description={group.description}>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {group.fields.map((field) => (
                                    <SecretField
                                        key={field.name}
                                        label={field.label}
                                        value={draft[field.name] ?? ''}
                                        isSet={secrets[field.name] === true}
                                        mask={mask}
                                        error={errors[field.name]}
                                        onChange={(value) => setDraft((current) => ({ ...current, [field.name]: value }))}
                                    />
                                ))}
                            </div>
                        </PanelCard>
                    );
                })}

                <FormActions
                    dirty={dirty}
                    submitting={submitting}
                    saved={saved}
                    submitLabel="Save credentials"
                    cancelLabel="Clear"
                    onCancel={() => setDraft(blankDraft(known))}
                />
            </form>
        </AdminSettingsLayout>
    );
}
