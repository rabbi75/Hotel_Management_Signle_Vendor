import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from '@/components/ui/toast';
import { SettingsLayout } from '@/layouts/settings-layout';
import type { SharedProps } from '@/types';
import type { RecoveryCodesResponse, TwoFactorPageProps } from '@/types/auth';
import { router, useForm, usePage } from '@inertiajs/react';
import { Copy, RefreshCw, ShieldCheck, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';

type CodesState = { status: 'idle' | 'loading' | 'error'; codes: string[] };

async function copyToClipboard(text: string): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        return false;
    }
}

export default function TwoFactorSettingsPage({
    enabled,
    confirmed,
    pending,
    requiresConfirmation,
    qrCodeSvg,
    secret,
}: TwoFactorPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const confirmDialog = useConfirm();

    const [busy, setBusy] = useState(false);
    const [codes, setCodes] = useState<CodesState>({ status: 'idle', codes: [] });

    const confirmForm = useForm<{ code: string; [key: string]: string }>({ code: '' });

    const active = enabled && (!requiresConfirmation || confirmed);

    const loadCodes = useCallback(async (): Promise<void> => {
        setCodes({ status: 'loading', codes: [] });

        try {
            const response = await fetch(route('settings.two-factor.recovery-codes'), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const payload = (await response.json()) as RecoveryCodesResponse;

            setCodes({ status: 'idle', codes: payload.codes });
        } catch {
            setCodes({ status: 'error', codes: [] });
        }
    }, []);

    useEffect(() => {
        if (active) {
            void loadCodes();
        }
    }, [active, loadCodes]);

    function startEnrolment(): void {
        setBusy(true);
        router.post(route('settings.two-factor.store'), {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    function submitConfirmation(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        confirmForm.post(route('settings.two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirmForm.reset('code'),
        });
    }

    async function regenerate(): Promise<void> {
        const ok = await confirmDialog({
            title: 'Generate new recovery codes?',
            description: 'Your existing codes stop working immediately. Save the new ones somewhere safe.',
            confirmLabel: 'Generate new codes',
            variant: 'destructive',
        });

        if (!ok) {
            return;
        }

        setBusy(true);
        router.post(
            route('settings.two-factor.recovery-codes.regenerate'),
            {},
            {
                preserveScroll: true,
                onSuccess: () => void loadCodes(),
                onFinish: () => setBusy(false),
            },
        );
    }

    async function disable(): Promise<void> {
        const ok = await confirmDialog({
            title: 'Turn off two-factor authentication?',
            description: 'Your account will be protected by its password alone.',
            confirmLabel: 'Turn it off',
            variant: 'destructive',
            confirmWord: 'disable',
        });

        if (!ok) {
            return;
        }

        setBusy(true);
        router.delete(route('settings.two-factor.destroy'), { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    return (
        <SettingsLayout title="Two-factor authentication" description="A second step at sign-in, from an authenticator app on your phone.">
            <ErrorSummary errors={errors} handled={['code']} />

            <PanelCard
                title="Status"
                description={active ? 'Your account asks for a code at every sign-in.' : 'Your account is protected by its password alone.'}
                action={<Badge variant={active ? 'success' : 'outline'}>{active ? 'On' : 'Off'}</Badge>}
            >
                {active ? (
                    <div className="flex flex-wrap items-center gap-3">
                        <ShieldCheck className="size-5 text-success" aria-hidden="true" />
                        <p className="min-w-0 flex-1 text-sm text-muted-foreground">Two-factor authentication is active on this account.</p>
                        <Button type="button" variant="destructive" onClick={() => void disable()} disabled={busy}>
                            Turn off
                        </Button>
                    </div>
                ) : pending ? (
                    <Alert variant="warning" role="status">
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>Enrolment is not finished</AlertTitle>
                        <AlertDescription>Scan the QR code below and enter a code to activate it.</AlertDescription>
                    </Alert>
                ) : (
                    <Button type="button" onClick={startEnrolment} loading={busy} disabled={busy}>
                        Set up two-factor authentication
                    </Button>
                )}
            </PanelCard>

            {pending && (
                <PanelCard title="Scan this code" description="Open your authenticator app and add a new account with this QR code.">
                    <div className="flex flex-col gap-6 sm:flex-row sm:items-start">
                        <div
                            className="inline-flex shrink-0 rounded-lg border border-border bg-card p-3 [&_svg]:size-40"
                            role="img"
                            aria-label="Two-factor setup QR code"
                            // Rendered by the server from the enrolment secret; there is no
                            // user-supplied content in this markup.
                            dangerouslySetInnerHTML={{ __html: qrCodeSvg ?? '' }}
                        />

                        <div className="min-w-0 flex-1 space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="setup-key">Or enter this key manually</Label>
                                <div className="flex items-center gap-2">
                                    <code
                                        id="setup-key"
                                        className="min-w-0 flex-1 overflow-x-auto rounded-md border border-border bg-muted px-3 py-2 font-mono text-sm break-all"
                                    >
                                        {secret ?? '—'}
                                    </code>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="Copy setup key"
                                        disabled={!secret}
                                        onClick={() =>
                                            void copyToClipboard(secret ?? '').then((ok) =>
                                                ok ? toast.success('Setup key copied.') : toast.error('Could not copy the setup key.'),
                                            )
                                        }
                                    >
                                        <Copy aria-hidden="true" />
                                    </Button>
                                </div>
                            </div>

                            <form onSubmit={submitConfirmation} noValidate className="space-y-3">
                                <Label htmlFor="two-factor-code">Enter the 6-digit code</Label>
                                <InputOTP
                                    id="two-factor-code"
                                    maxLength={6}
                                    value={confirmForm.data.code}
                                    aria-invalid={Boolean(errors.code)}
                                    aria-describedby={errors.code ? 'two-factor-code-error' : undefined}
                                    onChange={(value) => confirmForm.setData('code', value)}
                                >
                                    <InputOTPGroup>
                                        <InputOTPSlot index={0} />
                                        <InputOTPSlot index={1} />
                                        <InputOTPSlot index={2} />
                                    </InputOTPGroup>
                                    <InputOTPSeparator aria-hidden="true" />
                                    <InputOTPGroup>
                                        <InputOTPSlot index={3} />
                                        <InputOTPSlot index={4} />
                                        <InputOTPSlot index={5} />
                                    </InputOTPGroup>
                                </InputOTP>

                                {errors.code && (
                                    <p id="two-factor-code-error" className="text-sm font-medium text-destructive">
                                        {errors.code}
                                    </p>
                                )}

                                <Button
                                    type="submit"
                                    loading={confirmForm.processing}
                                    disabled={confirmForm.processing || confirmForm.data.code.length < 6}
                                >
                                    Activate
                                </Button>
                            </form>
                        </div>
                    </div>
                </PanelCard>
            )}

            {active && (
                <PanelCard
                    title="Recovery codes"
                    description="Each code works once. Keep them somewhere you can reach without your phone."
                    action={
                        <Button type="button" variant="outline" size="sm" onClick={() => void regenerate()} disabled={busy}>
                            <RefreshCw aria-hidden="true" />
                            Regenerate
                        </Button>
                    }
                >
                    {codes.status === 'loading' ? (
                        <div className="grid gap-2 sm:grid-cols-2" aria-hidden="true">
                            {Array.from({ length: 8 }, (_, index) => (
                                <Skeleton key={index} className="h-9 w-full" />
                            ))}
                        </div>
                    ) : codes.status === 'error' ? (
                        <Alert variant="destructive" role="alert">
                            <TriangleAlert aria-hidden="true" />
                            <AlertTitle>Recovery codes could not be loaded</AlertTitle>
                            <AlertDescription>
                                <Button type="button" variant="outline" size="sm" className="mt-2" onClick={() => void loadCodes()}>
                                    Try again
                                </Button>
                            </AlertDescription>
                        </Alert>
                    ) : codes.codes.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No recovery codes are stored for this account.</p>
                    ) : (
                        <>
                            <ul className="grid gap-2 sm:grid-cols-2">
                                {codes.codes.map((code) => (
                                    <li
                                        key={code}
                                        className="rounded-md border border-border bg-muted px-3 py-2 font-mono text-sm break-all"
                                    >
                                        {code}
                                    </li>
                                ))}
                            </ul>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="mt-4"
                                onClick={() =>
                                    void copyToClipboard(codes.codes.join('\n')).then((ok) =>
                                        ok ? toast.success('Recovery codes copied.') : toast.error('Could not copy the recovery codes.'),
                                    )
                                }
                            >
                                <Copy aria-hidden="true" />
                                Copy all codes
                            </Button>
                        </>
                    )}
                </PanelCard>
            )}
        </SettingsLayout>
    );
}
