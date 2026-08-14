import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { AuthLayout } from '@/layouts/auth-layout';
import type { SharedProps } from '@/types';
import type { InvitationShowPageProps, InvitationState } from '@/types/auth';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Ban, CircleAlert, Clock, MailX, UserCheck, type LucideIcon } from 'lucide-react';
import type { FormEvent } from 'react';

interface StateCopy {
    icon: LucideIcon;
    variant: 'success' | 'warning' | 'destructive' | 'info';
    title: string;
    body: string;
}

const STATE_COPY: Record<Exclude<InvitationState, 'acceptable'>, StateCopy> = {
    accepted: {
        icon: UserCheck,
        variant: 'success',
        title: 'This invitation has already been accepted',
        body: 'Nothing more to do — the workspace is already available from your workspace switcher.',
    },
    expired: {
        icon: Clock,
        variant: 'warning',
        title: 'This invitation has expired',
        body: 'Invitation links are short-lived. Ask whoever invited you to send a new one.',
    },
    revoked: {
        icon: Ban,
        variant: 'destructive',
        title: 'This invitation was revoked',
        body: 'An administrator withdrew this invitation. Contact them if you think that was a mistake.',
    },
    email_mismatch: {
        icon: MailX,
        variant: 'destructive',
        title: 'This invitation is for a different account',
        body: 'Sign in with the invited address, or ask for a new invitation to the address you use.',
    },
};

function formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

export default function InvitationShow({ invitation, state, token }: InvitationShowPageProps) {
    const { auth, errors } = usePage<SharedProps>().props;
    const { post, processing } = useForm({});

    const workspace = invitation.company ?? 'a workspace';
    const dashboard = routeUrl('dashboard') ?? routeUrl('home') ?? '/';
    const errorMessage = Object.values(errors)[0] ?? null;

    function accept(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        post(route('invitations.accept', { invitation: token }));
    }

    return (
        <AuthLayout
            title={state === 'acceptable' ? `Join ${workspace}` : 'Invitation'}
            description={
                state === 'acceptable' ? (
                    <>
                        {invitation.invited_by ? `${invitation.invited_by} invited you` : 'You have been invited'} to collaborate in{' '}
                        <span className="font-medium text-foreground">{workspace}</span>.
                    </>
                ) : undefined
            }
        >
            <dl className="grid gap-3 text-sm">
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-muted-foreground">Workspace</dt>
                    <dd className="min-w-0 truncate font-medium">{workspace}</dd>
                </div>
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-muted-foreground">Invited address</dt>
                    <dd className="min-w-0 truncate font-medium">{invitation.email}</dd>
                </div>
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-muted-foreground">Role</dt>
                    <dd>
                        <Badge variant="secondary">{invitation.role_label}</Badge>
                    </dd>
                </div>
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-muted-foreground">{invitation.accepted_at ? 'Accepted' : 'Expires'}</dt>
                    <dd className="font-medium">{formatDate(invitation.accepted_at ?? invitation.expires_at)}</dd>
                </div>
            </dl>

            <Separator className="my-6" />

            {errorMessage && (
                <Alert variant="destructive" className="mb-6" role="alert">
                    <CircleAlert aria-hidden="true" />
                    <AlertDescription>{errorMessage}</AlertDescription>
                </Alert>
            )}

            {state === 'acceptable' ? (
                <form onSubmit={accept} className="space-y-3">
                    <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                        Accept invitation
                    </Button>
                    <Button variant="ghost" className="w-full" asChild>
                        <Link href={dashboard}>Not now</Link>
                    </Button>
                </form>
            ) : (
                <StateBlock state={state} invitedEmail={invitation.email} currentEmail={auth.user?.email ?? null} dashboard={dashboard} />
            )}
        </AuthLayout>
    );
}

interface StateBlockProps {
    state: Exclude<InvitationState, 'acceptable'>;
    invitedEmail: string;
    currentEmail: string | null;
    dashboard: string;
}

function StateBlock({ state, invitedEmail, currentEmail, dashboard }: StateBlockProps) {
    const copy = STATE_COPY[state];
    const Icon = copy.icon;

    return (
        <div className="space-y-4">
            <Alert variant={copy.variant} role="status">
                <Icon aria-hidden="true" />
                <AlertTitle>{copy.title}</AlertTitle>
                <AlertDescription>{copy.body}</AlertDescription>
            </Alert>

            {state === 'email_mismatch' && (
                <p className="text-sm text-muted-foreground">
                    Invited: <span className="font-medium text-foreground">{invitedEmail}</span>
                    {currentEmail && (
                        <>
                            {' · '}Signed in as: <span className="font-medium text-foreground">{currentEmail}</span>
                        </>
                    )}
                </p>
            )}

            <div className="grid gap-2">
                {state === 'email_mismatch' ? (
                    <Button className="w-full" onClick={() => router.post(route('logout'))}>
                        Sign out and use another account
                    </Button>
                ) : (
                    <Button className="w-full" asChild>
                        <Link href={dashboard}>{state === 'accepted' ? 'Go to your workspace' : 'Continue to the app'}</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
