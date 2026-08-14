import { Icon } from '@/components/app-shell/icon';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SharedProps } from '@/types';
import type { MaintenanceSettingsPageProps } from '@/types/settings';
import { router, usePage } from '@inertiajs/react';
import { CircleAlert, KeyRound, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';
import { SecretField } from '@/components/forms/secret-field';
import { linesToList, listToLines, untouchedSecret } from './use-settings-form';

const HANDLED = ['message', 'retry_after', 'secret', 'allowed_ips'];

export default function MaintenanceSettingsPage({ settings, secrets, generatedSecret }: MaintenanceSettingsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const confirm = useConfirm();

    const [message, setMessage] = useState(settings.message ?? '');
    const [retryAfter, setRetryAfter] = useState(String(settings.retry_after ?? 3600));
    const [secret, setSecret] = useState('');
    const [allowedIps, setAllowedIps] = useState(listToLines(settings.allowed_ips));
    const [busy, setBusy] = useState(false);
    const [copied, setCopied] = useState(false);

    async function copySecret(): Promise<void> {
        if (generatedSecret === null) {
            return;
        }

        await navigator.clipboard.writeText(generatedSecret);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    function payload() {
        return {
            message: message.trim() === '' ? null : message,
            retry_after: retryAfter === '' ? null : Number(retryAfter),
            secret: untouchedSecret(secret),
            allowed_ips: linesToList(allowedIps),
        };
    }

    async function enable(): Promise<void> {
        const ok = await confirm({
            title: 'Put the application into maintenance mode?',
            description: 'Everyone without a bypass token or an allow-listed IP address will be locked out until you turn this off.',
            variant: 'destructive',
            confirmWord: 'maintenance',
            confirmLabel: 'Enable maintenance mode',
        });

        if (!ok) {
            return;
        }

        setBusy(true);
        router.post(route('admin.settings.maintenance.enable'), payload(), {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    function update(): void {
        setBusy(true);
        router.post(route('admin.settings.maintenance.enable'), payload(), {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    function disable(): void {
        setBusy(true);
        router.delete(route('admin.settings.maintenance.disable'), {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    return (
        <AdminSettingsLayout title="Maintenance" description="Take the application offline for everyone except the people you name here.">
            <ErrorSummary errors={errors} handled={HANDLED} />

            {generatedSecret !== null && (
                <Alert role="status" className="mb-6">
                    <KeyRound aria-hidden="true" />
                    <AlertTitle>Copy your bypass token now</AlertTitle>
                    <AlertDescription className="space-y-3">
                        <p>
                            This is the only time it will be shown. Append{' '}
                            <code className="font-mono">?maintenance_secret={generatedSecret}</code> to any URL once to set a bypass cookie
                            for your browser.
                        </p>
                        <div className="flex w-full flex-wrap items-center gap-2">
                            <code className="min-w-0 flex-1 overflow-x-auto rounded-md bg-muted px-3 py-2 font-mono text-sm break-all">
                                {generatedSecret}
                            </code>
                            <Button type="button" variant="outline" size="sm" onClick={() => void copySecret()}>
                                <Icon name={copied ? 'check' : 'copy'} className="size-4" />
                                {copied ? 'Copied' : 'Copy'}
                            </Button>
                        </div>
                    </AlertDescription>
                </Alert>
            )}

            <PanelCard
                title="Status"
                description="Unlike `artisan down`, this flag survives a deploy and is recorded in the audit trail."
                action={<Badge variant={settings.enabled ? 'destructive' : 'success'}>{settings.enabled ? 'Offline' : 'Online'}</Badge>}
            >
                {settings.enabled ? (
                    <Alert variant="destructive" role="status">
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>Maintenance mode is on</AlertTitle>
                        <AlertDescription>
                            Visitors are seeing the maintenance screen. Only allow-listed addresses can reach the app.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <p className="text-sm text-muted-foreground">The application is reachable normally.</p>
                )}

                <div className="mt-4 flex flex-wrap gap-2">
                    {settings.enabled ? (
                        <>
                            <Button type="button" onClick={disable} loading={busy} disabled={busy}>
                                Bring the application back online
                            </Button>
                            <Button type="button" variant="outline" onClick={update} disabled={busy}>
                                Save these settings
                            </Button>
                        </>
                    ) : (
                        <Button type="button" variant="destructive" onClick={() => void enable()} loading={busy} disabled={busy}>
                            Enable maintenance mode
                        </Button>
                    )}
                </div>
            </PanelCard>

            <PanelCard title="Maintenance screen" description="What visitors see, and who can get past it.">
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="maintenance-message">Message</Label>
                        <Textarea
                            id="maintenance-message"
                            rows={3}
                            value={message}
                            placeholder="We are upgrading the database and will be back shortly."
                            aria-invalid={Boolean(errors.message)}
                            aria-describedby={errors.message ? 'maintenance-message-error' : undefined}
                            onChange={(event) => setMessage(event.target.value)}
                        />
                        {errors.message && (
                            <p id="maintenance-message-error" className="text-sm font-medium text-destructive">
                                {errors.message}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="maintenance-retry">Retry-After header (seconds)</Label>
                        <Input
                            id="maintenance-retry"
                            type="number"
                            min={0}
                            max={86400}
                            value={retryAfter}
                            aria-invalid={Boolean(errors.retry_after)}
                            aria-describedby={`maintenance-retry-description${errors.retry_after ? ' maintenance-retry-error' : ''}`}
                            onChange={(event) => setRetryAfter(event.target.value)}
                        />
                        <p id="maintenance-retry-description" className="text-xs text-muted-foreground">
                            Tells crawlers and monitors when to come back.
                        </p>
                        {errors.retry_after && (
                            <p id="maintenance-retry-error" className="text-sm font-medium text-destructive">
                                {errors.retry_after}
                            </p>
                        )}
                    </div>

                    <SecretField
                        label="Bypass token"
                        value={secret}
                        isSet={secrets.secret === true}
                        mask="********"
                        error={errors.secret}
                        autoComplete="off"
                        description={
                            secrets.secret === true
                                ? 'A token is stored. Leave blank to keep it, or type a new one to replace it.'
                                : 'Leave blank and one will be generated for you when maintenance mode is enabled.'
                        }
                        onChange={setSecret}
                    />

                    <Alert>
                        <CircleAlert aria-hidden="true" />
                        <AlertDescription>
                            Append <code className="font-mono">?maintenance_secret=&lt;token&gt;</code> to any URL once to set a bypass
                            cookie for this browser.
                        </AlertDescription>
                    </Alert>

                    <div className="grid gap-2">
                        <Label htmlFor="maintenance-ips">IP allow-list</Label>
                        <Textarea
                            id="maintenance-ips"
                            rows={4}
                            value={allowedIps}
                            placeholder={'198.51.100.4\n203.0.113.9'}
                            className="font-mono text-sm"
                            aria-invalid={Object.keys(errors).some((key) => key.startsWith('allowed_ips'))}
                            aria-describedby={`maintenance-ips-description${errors.allowed_ips ? ' maintenance-ips-error' : ''}`}
                            onChange={(event) => setAllowedIps(event.target.value)}
                        />
                        <p id="maintenance-ips-description" className="text-xs text-muted-foreground">
                            One address per line. These addresses always reach the application.
                        </p>
                        {Object.entries(errors)
                            .filter(([key]) => key.startsWith('allowed_ips'))
                            .map(([key, error]) => (
                                <p key={key} id="maintenance-ips-error" className="text-sm font-medium text-destructive">
                                    {error}
                                </p>
                            ))}
                    </div>
                </div>
            </PanelCard>
        </AdminSettingsLayout>
    );
}
