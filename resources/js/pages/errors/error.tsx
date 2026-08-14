import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Ban, Clock, Construction, FileQuestion, Lock, ServerCrash } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

interface StatusDefinition {
    title: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
}

const STATUSES: Record<number, StatusDefinition> = {
    401: {
        title: 'You need to sign in',
        description: 'This page is only available to signed-in users. Log in and we will bring you straight back.',
        icon: Lock,
    },
    403: {
        title: 'You do not have access',
        description: 'Your account exists, but it lacks the permission this page requires. Ask a workspace owner to grant it.',
        icon: Ban,
    },
    404: {
        title: 'We could not find that page',
        description: 'The link may be out of date, or the record it pointed at has since been deleted.',
        icon: FileQuestion,
    },
    429: {
        title: 'Slow down for a moment',
        description: 'Too many requests arrived in a short window. Wait a few seconds and try again.',
        icon: Clock,
    },
    500: {
        title: 'Something went wrong on our side',
        description: 'The error has been logged and the team notified. Trying again often works.',
        icon: ServerCrash,
    },
    503: {
        title: 'Down for maintenance',
        description: 'We are deploying an update. This usually takes a couple of minutes.',
        icon: Construction,
    },
};

const FALLBACK: StatusDefinition = {
    title: 'Unexpected error',
    description: 'Something did not go to plan. Try again, or head back to safety.',
    icon: ServerCrash,
};

export interface ErrorPageProps {
    status: number;
    message: string | null;
}

export default function ErrorPage({ status, message }: ErrorPageProps) {
    const { auth } = usePage<SharedProps>().props;
    const definition = STATUSES[status] ?? FALLBACK;
    const authenticated = auth.user !== null;

    const homeUrl = (authenticated ? routeUrl('dashboard') : null) ?? '/';
    const loginUrl = routeUrl('login');

    const body = (
        <div className="mx-auto flex max-w-lg flex-col items-center gap-5 py-16 text-center">
            <span className="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                <definition.icon className="size-7" aria-hidden="true" />
            </span>

            <p className="font-mono text-sm font-medium tracking-widest text-muted-foreground">{status}</p>

            <h1 className="text-2xl font-semibold tracking-tight text-balance sm:text-3xl">{definition.title}</h1>
            <p className="text-balance text-muted-foreground">{message ?? definition.description}</p>

            <div className="mt-2 flex flex-wrap justify-center gap-3">
                <Button variant="outline" onClick={() => window.history.back()}>
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    Go back
                </Button>

                {status === 401 && loginUrl && !authenticated ? (
                    <Button asChild>
                        <Link href={loginUrl}>Log in</Link>
                    </Button>
                ) : (
                    <Button asChild>
                        <Link href={homeUrl}>{authenticated ? 'Back to dashboard' : 'Back to home'}</Link>
                    </Button>
                )}
            </div>
        </div>
    );

    if (authenticated) {
        return (
            <AppLayout title={`${status} — ${definition.title}`} breadcrumbs={[{ label: 'Error' }]}>
                {body}
            </AppLayout>
        );
    }

    return (
        <Standalone title={`${status} — ${definition.title}`}>
            <div className="flex min-h-svh items-center justify-center px-4">{body}</div>
        </Standalone>
    );
}

function Standalone({ title, children }: { title: string; children: ReactNode }) {
    return (
        <>
            <Head title={title} />
            {children}
        </>
    );
}
