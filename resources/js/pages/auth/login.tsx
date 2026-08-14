import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { AuthLayout } from '@/layouts/auth-layout';
import type { LoginPageProps } from '@/types/auth';
import { Link, useForm } from '@inertiajs/react';
import { CircleCheck, Eye, EyeOff } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
    [key: string]: string | boolean;
}

const PROVIDER_LABELS: Record<string, string> = {
    google: 'Google',
    github: 'GitHub',
    facebook: 'Facebook',
    gitlab: 'GitLab',
    bitbucket: 'Bitbucket',
    linkedin: 'LinkedIn',
    twitter: 'X',
};

function providerLabel(provider: string): string {
    return PROVIDER_LABELS[provider] ?? provider.charAt(0).toUpperCase() + provider.slice(1);
}

export default function Login({ canResetPassword, canRegister, status, socials }: LoginPageProps) {
    const [revealed, setRevealed] = useState(false);

    const form = useForm<LoginForm>({ email: '', password: '', remember: false });
    const { data, setData, post, processing, errors } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        post(route('login.store'), { onFinish: () => form.reset('password') });
    }

    const providers = socials.map((provider) => ({ provider, href: routeUrl('social.redirect', provider) })).filter((entry) => entry.href);

    return (
        <AuthLayout
            title="Sign in"
            description="Welcome back. Enter your details to continue."
            footer={
                canRegister ? (
                    <>
                        Don&rsquo;t have an account?{' '}
                        <Link href={route('register')} className="font-medium text-foreground underline-offset-4 hover:underline">
                            Create one
                        </Link>
                    </>
                ) : undefined
            }
        >
            {status && (
                <Alert variant="success" className="mb-6" role="status">
                    <CircleCheck aria-hidden="true" />
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <form onSubmit={submit} noValidate className="space-y-5">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={errors.email ? 'email-error' : undefined}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email && (
                        <p id="email-error" className="text-sm font-medium text-destructive">
                            {errors.email}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor="password">Password</Label>
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="rounded-sm text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <div className="relative">
                        <Input
                            id="password"
                            type={revealed ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            autoComplete="current-password"
                            required
                            className="pr-10"
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={errors.password ? 'password-error' : undefined}
                            onChange={(event) => setData('password', event.target.value)}
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            className="absolute top-1/2 right-1 -translate-y-1/2"
                            aria-pressed={revealed}
                            aria-label={revealed ? 'Hide password' : 'Show password'}
                            onClick={() => setRevealed((current) => !current)}
                        >
                            {revealed ? <EyeOff aria-hidden="true" /> : <Eye aria-hidden="true" />}
                        </Button>
                    </div>

                    {errors.password && (
                        <p id="password-error" className="text-sm font-medium text-destructive">
                            {errors.password}
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <Checkbox id="remember" checked={data.remember} onCheckedChange={(checked) => setData('remember', checked === true)} />
                    <Label htmlFor="remember" className="text-sm font-normal">
                        Keep me signed in
                    </Label>
                </div>

                <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                    Sign in
                </Button>
            </form>

            {providers.length > 0 && (
                <>
                    <div className="my-6 flex items-center gap-3">
                        <Separator className="flex-1" />
                        <span className="text-xs tracking-wide text-muted-foreground uppercase">or</span>
                        <Separator className="flex-1" />
                    </div>

                    <div className="grid gap-2">
                        {providers.map(({ provider, href }) => (
                            <Button key={provider} variant="outline" className="w-full" asChild>
                                <a href={href ?? '#'}>Continue with {providerLabel(provider)}</a>
                            </Button>
                        ))}
                    </div>
                </>
            )}
        </AuthLayout>
    );
}
