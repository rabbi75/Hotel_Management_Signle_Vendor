import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { AuthLayout } from '@/layouts/auth-layout';
import type { SharedProps } from '@/types';
import type { VerifyEmailPageProps } from '@/types/auth';
import { router, useForm, usePage } from '@inertiajs/react';
import { CircleCheck, MailCheck } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

/** Matches Fortify's own throttle on the verification-notification route. */
const COOLDOWN_SECONDS = 60;

export default function VerifyEmail({ status }: VerifyEmailPageProps) {
    const { auth, errors } = usePage<SharedProps>().props;
    const [cooldown, setCooldown] = useState(0);

    const { post, processing } = useForm({});

    useEffect(() => {
        if (cooldown <= 0) {
            return;
        }

        const timer = window.setTimeout(() => setCooldown((seconds) => seconds - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [cooldown]);

    // A fresh `status` means the server accepted a resend, so the clock starts.
    useEffect(() => {
        if (status === 'verification-link-sent') {
            setCooldown(COOLDOWN_SECONDS);
        }
    }, [status]);

    function resend(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        post(route('verification.send'), { preserveScroll: true });
    }

    const errorMessage = Object.values(errors)[0] ?? null;

    return (
        <AuthLayout
            title="Verify your email"
            description={
                auth.user ? (
                    <>
                        We sent a verification link to <span className="font-medium text-foreground">{auth.user.email}</span>. Open it to
                        finish setting up your account.
                    </>
                ) : (
                    'Open the verification link we emailed you to finish setting up your account.'
                )
            }
        >
            {status === 'verification-link-sent' && (
                <Alert variant="success" className="mb-6" role="status">
                    <CircleCheck aria-hidden="true" />
                    <AlertDescription>A new verification link is on its way.</AlertDescription>
                </Alert>
            )}

            {errorMessage && (
                <Alert variant="destructive" className="mb-6" role="alert">
                    <AlertDescription>{errorMessage}</AlertDescription>
                </Alert>
            )}

            <div className="mb-6 flex items-center justify-center">
                <span className="flex size-14 items-center justify-center rounded-full bg-muted">
                    <MailCheck className="size-7 text-muted-foreground" aria-hidden="true" />
                </span>
            </div>

            <form onSubmit={resend} className="space-y-3">
                <Button type="submit" className="w-full" loading={processing} disabled={processing || cooldown > 0}>
                    {cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend verification email'}
                </Button>

                <p className="text-center text-xs text-muted-foreground" aria-live="polite">
                    {cooldown > 0 ? 'You can request another link once the timer runs out.' : 'Check your spam folder before resending.'}
                </p>

                <Button type="button" variant="ghost" className="w-full" onClick={() => router.post(route('logout'))}>
                    Sign out
                </Button>
            </form>
        </AuthLayout>
    );
}
