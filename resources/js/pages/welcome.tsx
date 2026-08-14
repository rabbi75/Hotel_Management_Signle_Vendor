import { BrandLogo } from '@/components/app-shell/brand-logo';
import { hasRoute, routeUrl } from '@/components/app-shell/routing';
import { ThemeToggle } from '@/components/app-shell/theme-toggle';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { SharedProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Building2,
    CreditCard,
    Github,
    KeyRound,
    Layers,
    Lock,
    ShieldCheck,
    Users,
    Webhook,
    Zap,
} from 'lucide-react';
import type { ComponentType } from 'react';

interface Feature {
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: string;
}

const FEATURES: Feature[] = [
    {
        icon: Building2,
        title: 'Multi-tenant workspaces',
        description: 'Companies, invitations and per-workspace data isolation are wired end to end from day one.',
    },
    {
        icon: ShieldCheck,
        title: 'Roles and permissions',
        description: 'Policy-backed authorisation with a permission matrix your customers can manage themselves.',
    },
    {
        icon: KeyRound,
        title: 'Complete auth',
        description: 'Registration, verification, password reset, two-factor and device sessions — all styled and tested.',
    },
    {
        icon: CreditCard,
        title: 'Billing ready',
        description: 'Plans, subscriptions and invoices modelled so you can plug in your provider and charge.',
    },
    {
        icon: BarChart3,
        title: 'Dashboards and tables',
        description: 'A production data table with server-side sort, filter and export, plus themed charts.',
    },
    {
        icon: Webhook,
        title: 'Realtime and webhooks',
        description: 'Broadcast notifications over websockets and sign outbound webhooks without extra plumbing.',
    },
    {
        icon: Layers,
        title: 'Modular by design',
        description: 'Every feature is a self-contained module with its own routes, policies and tests.',
    },
    {
        icon: Lock,
        title: 'Audited and hardened',
        description: 'Activity logging, rate limiting and a strict CSP baked into the request lifecycle.',
    },
];

const STATS = [
    { value: '40+', label: 'UI components' },
    { value: '9', label: 'Feature modules' },
    { value: '100%', label: 'TypeScript strict' },
    { value: 'A11y', label: 'Keyboard-first' },
];

export default function Welcome() {
    const { name, auth } = usePage<SharedProps>().props;

    const loginUrl = routeUrl('login');
    const registerUrl = routeUrl('register');
    const dashboardUrl = routeUrl('dashboard');
    const authenticated = auth.user !== null;

    return (
        <>
            <Head title="Build your SaaS faster" />

            <div className="relative min-h-svh overflow-x-hidden bg-background">
                <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[42rem]">
                    <div className="absolute -top-56 left-1/2 size-[52rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
                    <div className="absolute top-20 -right-40 size-[32rem] rounded-full bg-chart-5/10 blur-3xl" />
                    <div className="absolute top-40 -left-40 size-[32rem] rounded-full bg-chart-2/10 blur-3xl" />
                </div>

                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
                    <Link href="/" className="flex items-center rounded-md" aria-label={`${name} home`}>
                        <BrandLogo variant="landing" />
                    </Link>

                    <nav className="flex items-center gap-2" aria-label="Account">
                        <ThemeToggle />

                        {authenticated ? (
                            dashboardUrl && (
                                <Button asChild size="sm">
                                    <Link href={dashboardUrl}>
                                        Dashboard
                                        <ArrowRight className="size-4" aria-hidden="true" />
                                    </Link>
                                </Button>
                            )
                        ) : (
                            <>
                                {loginUrl && (
                                    <Button asChild variant="ghost" size="sm">
                                        <Link href={loginUrl}>Log in</Link>
                                    </Button>
                                )}
                                {registerUrl && (
                                    <Button asChild size="sm">
                                        <Link href={registerUrl}>Get started</Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto w-full max-w-6xl px-4 pt-12 pb-16 text-center sm:px-6 sm:pt-20">
                        <Badge variant="outline" className="mx-auto mb-6 gap-1.5 bg-card px-3 py-1">
                            <Zap className="size-3 text-primary" aria-hidden="true" />
                            Laravel 12 · React 19 · Tailwind v4
                        </Badge>

                        <h1 className="mx-auto max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-6xl">
                            The SaaS foundation you would have built anyway
                        </h1>

                        <p className="mx-auto mt-5 max-w-2xl text-lg text-balance text-muted-foreground">
                            Multi-tenancy, authentication, billing, permissions and an application shell that already feels finished. Start
                            from the part of the product that is actually yours.
                        </p>

                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                            {registerUrl && (
                                <Button asChild size="lg">
                                    <Link href={registerUrl}>
                                        Get started free
                                        <ArrowRight className="size-4" aria-hidden="true" />
                                    </Link>
                                </Button>
                            )}
                            {loginUrl && (
                                <Button asChild size="lg" variant="outline">
                                    <Link href={loginUrl}>Log in</Link>
                                </Button>
                            )}
                            {!registerUrl && !loginUrl && (
                                <Button asChild size="lg">
                                    <a href="https://github.com" target="_blank" rel="noreferrer noopener">
                                        <Github className="size-4" aria-hidden="true" />
                                        View the source
                                    </a>
                                </Button>
                            )}
                        </div>

                        <dl className="mx-auto mt-14 grid max-w-3xl grid-cols-2 gap-6 sm:grid-cols-4">
                            {STATS.map((stat) => (
                                <div key={stat.label} className="space-y-1">
                                    <dt className="sr-only">{stat.label}</dt>
                                    <dd className="text-2xl font-semibold tracking-tight">{stat.value}</dd>
                                    <p className="text-sm text-muted-foreground">{stat.label}</p>
                                </div>
                            ))}
                        </dl>
                    </section>

                    <section className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6" aria-labelledby="features-heading">
                        <div className="mx-auto max-w-2xl text-center">
                            <h2 id="features-heading" className="text-3xl font-semibold tracking-tight text-balance">
                                Everything the first six months would have cost you
                            </h2>
                            <p className="mt-3 text-muted-foreground">
                                Each piece is a real implementation with tests — not a placeholder waiting for you to finish it.
                            </p>
                        </div>

                        <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {FEATURES.map((feature) => (
                                <Card key={feature.title} className="h-full transition-shadow hover:shadow-md">
                                    <CardContent className="space-y-3 p-5">
                                        <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <feature.icon className="size-4" aria-hidden="true" />
                                        </span>
                                        <h3 className="font-medium">{feature.title}</h3>
                                        <p className="text-sm text-muted-foreground">{feature.description}</p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </section>

                    <section className="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6" aria-labelledby="cta-heading">
                        <div className="relative overflow-hidden rounded-2xl border border-border bg-card px-6 py-14 text-center shadow-sm">
                            <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10">
                                <div className="absolute -top-24 left-1/2 size-96 -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
                            </div>

                            <Users className="mx-auto size-8 text-primary" aria-hidden="true" />
                            <h2 id="cta-heading" className="mt-4 text-3xl font-semibold tracking-tight text-balance">
                                Ship the product, not the plumbing
                            </h2>
                            <p className="mx-auto mt-3 max-w-xl text-muted-foreground">
                                Clone it, rename it, and have a signed-in, multi-tenant application running this afternoon.
                            </p>

                            <div className="mt-7 flex flex-wrap justify-center gap-3">
                                {registerUrl && (
                                    <Button asChild size="lg">
                                        <Link href={registerUrl}>
                                            Create your workspace
                                            <ArrowRight className="size-4" aria-hidden="true" />
                                        </Link>
                                    </Button>
                                )}
                                {hasRoute('login') && loginUrl && (
                                    <Button asChild size="lg" variant="ghost">
                                        <Link href={loginUrl}>I already have an account</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </section>
                </main>

                <Separator />

                <footer className="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:px-6">
                    <p>
                        © {new Date().getFullYear()} {name}. All rights reserved.
                    </p>
                    <p>Built with Laravel, Inertia and React.</p>
                </footer>
            </div>
        </>
    );
}
