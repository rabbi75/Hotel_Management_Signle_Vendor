import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SettingsLayout } from '@/layouts/settings-layout';
import type { SharedProps } from '@/types';
import type { SessionEntry, SessionsPageProps } from '@/types/auth';
import { router, useForm, usePage } from '@inertiajs/react';
import { Info, Monitor, Smartphone, Tablet, type LucideIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';

const DEVICE_ICON: Record<string, LucideIcon> = {
    mobile: Smartphone,
    phone: Smartphone,
    tablet: Tablet,
    desktop: Monitor,
};

function deviceIcon(device: string | null): LucideIcon {
    return DEVICE_ICON[(device ?? 'desktop').toLowerCase()] ?? Monitor;
}

function describe(session: SessionEntry): string {
    return [session.browser, session.platform].filter(Boolean).join(' on ') || 'Unknown device';
}

export default function SessionsPage({ sessions, supported }: SessionsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const confirm = useConfirm();

    const [revoking, setRevoking] = useState<string | null>(null);
    const others = useForm<{ password: string; [key: string]: string }>({ password: '' });

    const otherCount = sessions.filter((session) => !session.is_current).length;

    async function revoke(session: SessionEntry): Promise<void> {
        const ok = await confirm({
            title: 'Sign out this device?',
            description: `${describe(session)} will have to sign in again.`,
            confirmLabel: 'Sign it out',
            variant: 'destructive',
        });

        if (!ok) {
            return;
        }

        setRevoking(session.id);
        router.delete(route('settings.sessions.destroy', { session: session.id }), {
            preserveScroll: true,
            onFinish: () => setRevoking(null),
        });
    }

    async function revokeOthers(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();

        const ok = await confirm({
            title: 'Sign out every other device?',
            description: 'All other browsers and remembered devices will be signed out immediately.',
            confirmLabel: 'Sign them all out',
            variant: 'destructive',
        });

        if (!ok) {
            return;
        }

        others.delete(route('settings.sessions.destroy-others'), {
            preserveScroll: true,
            onSuccess: () => others.reset('password'),
        });
    }

    return (
        <SettingsLayout title="Sessions" description="Every browser currently signed in to your account.">
            <ErrorSummary errors={errors} handled={['password', 'session']} />

            {!supported && (
                <Alert className="mb-6">
                    <Info aria-hidden="true" />
                    <AlertTitle>Session listing is unavailable</AlertTitle>
                    <AlertDescription>
                        This installation does not store sessions in the database, so individual devices cannot be listed. You can still
                        sign out every other device below.
                    </AlertDescription>
                </Alert>
            )}

            <PanelCard title="Active devices" description="Revoke anything you do not recognise.">
                {supported && sessions.length === 0 ? (
                    <EmptyState
                        icon={Monitor}
                        title="No other sessions"
                        description="This is the only browser signed in to your account."
                    />
                ) : (
                    <ul className="divide-y divide-border">
                        {sessions.map((session) => {
                            const Icon = deviceIcon(session.device);

                            return (
                                <li key={session.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                        <Icon className="size-5 text-muted-foreground" aria-hidden="true" />
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                            <span className="truncate">{describe(session)}</span>
                                            {session.is_current && <Badge variant="success">This device</Badge>}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {session.ip_address ?? 'Unknown address'} · last active {session.last_active_human}
                                        </p>
                                    </div>

                                    {!session.is_current && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="self-start sm:self-auto"
                                            loading={revoking === session.id}
                                            disabled={revoking !== null}
                                            onClick={() => void revoke(session)}
                                        >
                                            Sign out
                                        </Button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </PanelCard>

            <PanelCard
                title="Sign out everywhere else"
                description="Confirm with your password. This also clears every remembered-device cookie."
            >
                <form onSubmit={(event) => void revokeOthers(event)} noValidate className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="others-password">Current password</Label>
                        <Input
                            id="others-password"
                            type="password"
                            value={others.data.password}
                            autoComplete="current-password"
                            required
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={errors.password ? 'others-password-error' : undefined}
                            onChange={(event) => others.setData('password', event.target.value)}
                        />
                        {errors.password && (
                            <p id="others-password-error" className="text-sm font-medium text-destructive">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    <Button
                        type="submit"
                        variant="destructive"
                        loading={others.processing}
                        disabled={others.processing || others.data.password === '' || (supported && otherCount === 0)}
                    >
                        Sign out other devices
                    </Button>
                </form>
            </PanelCard>
        </SettingsLayout>
    );
}
