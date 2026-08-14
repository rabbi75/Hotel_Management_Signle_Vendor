import { FormBuilder } from '@/components/forms/form-builder';
import type { FormFieldSchema } from '@/components/forms/schema';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SelectOption, SharedProps } from '@/types';
import type { MailSettings, MailSettingsPageProps } from '@/types/settings';
import { useForm, usePage } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useMemo, type FormEvent } from 'react';
import type { DefaultValues } from 'react-hook-form';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';
import { untouchedSecret, useSettingsSubmit } from './use-settings-form';

const ENCRYPTION: SelectOption[] = [
    { value: 'tls', label: 'TLS' },
    { value: 'ssl', label: 'SSL' },
    { value: 'none', label: 'None' },
];

/** Only SMTP needs a host and credentials; the API transports are configured by key. */
const SMTP_ONLY = { field: 'mailer', equals: 'smtp' } as const;

interface TestMailForm {
    recipient: string;
    [key: string]: string;
}

export default function MailSettingsPage({ settings, secrets, transports }: MailSettingsPageProps) {
    const { errors, auth } = usePage<SharedProps>().props;
    const { submit, submitting, saved } = useSettingsSubmit(route('admin.settings.mail.update'));

    const passwordIsSet = secrets.password === true;

    const fields = useMemo<FormFieldSchema[]>(
        () => [
            {
                name: 'mailer',
                label: 'Transport',
                type: 'select',
                required: true,
                options: transports.map((transport) => ({ value: transport, label: transport })),
            },
            { name: 'encryption', label: 'Encryption', type: 'select', options: ENCRYPTION, showWhen: SMTP_ONLY },
            { name: 'host', label: 'Host', type: 'text', placeholder: 'smtp.example.com', showWhen: SMTP_ONLY },
            { name: 'port', label: 'Port', type: 'number', min: 1, max: 65535, showWhen: SMTP_ONLY },
            { name: 'username', label: 'Username', type: 'text', autoComplete: 'off', showWhen: SMTP_ONLY },
            {
                name: 'password',
                label: 'Password',
                type: 'password',
                autoComplete: 'new-password',
                showWhen: SMTP_ONLY,
                description: passwordIsSet ? 'A password is stored. Leave blank to keep it.' : 'No password stored yet.',
            },
            { name: 'from_address', label: 'From address', type: 'email', required: true },
            { name: 'from_name', label: 'From name', type: 'text', required: true },
        ],
        [passwordIsSet, transports],
    );

    const handled = fields.map((field) => field.name);

    // The stored password never reaches the browser, so the input starts empty
    // and an empty input is submitted as null, which the server reads as
    // "unchanged" rather than "clear".
    const defaults = { ...settings, password: '' } as DefaultValues<MailSettings>;

    const test = useForm<TestMailForm>({ recipient: auth.user?.email ?? '' });

    function sendTest(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        test.post(route('admin.settings.mail.test'), { preserveScroll: true });
    }

    const transportError = errors.mail ?? null;

    return (
        <AdminSettingsLayout title="Mail" description="How this installation sends email.">
            <ErrorSummary errors={errors} handled={[...handled, 'mail', 'recipient']} />

            <PanelCard title="Delivery" description="Applied to every message the application sends.">
                <FormBuilder<MailSettings>
                    schema={fields}
                    defaultValues={defaults}
                    errors={errors}
                    submitting={submitting}
                    saved={saved}
                    onSubmit={(values, form) =>
                        submit({ ...values, password: untouchedSecret(values.password) }, () => form.reset({ ...values, password: '' }))
                    }
                />
            </PanelCard>

            <PanelCard title="Send a test email" description="Uses the settings that are currently saved, not the ones typed above.">
                {transportError && (
                    <Alert variant="destructive" role="alert" className="mb-4">
                        <CircleAlert aria-hidden="true" />
                        <AlertTitle>The transport rejected the message</AlertTitle>
                        <AlertDescription>
                            {/* Verbatim: the transport's own wording is the only thing that
                                tells an administrator what to fix. */}
                            <code className="block overflow-x-auto font-mono text-xs break-words whitespace-pre-wrap">
                                {transportError}
                            </code>
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={sendTest} noValidate className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="recipient">Recipient</Label>
                        <Input
                            id="recipient"
                            type="email"
                            value={test.data.recipient}
                            autoComplete="email"
                            required
                            aria-invalid={Boolean(errors.recipient)}
                            aria-describedby={errors.recipient ? 'recipient-error' : undefined}
                            onChange={(event) => test.setData('recipient', event.target.value)}
                        />
                        {errors.recipient && (
                            <p id="recipient-error" className="text-sm font-medium text-destructive">
                                {errors.recipient}
                            </p>
                        )}
                    </div>

                    <Button type="submit" variant="outline" loading={test.processing} disabled={test.processing}>
                        Send test email
                    </Button>
                </form>
            </PanelCard>
        </AdminSettingsLayout>
    );
}
