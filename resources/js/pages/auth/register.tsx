import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { AuthLayout } from '@/layouts/auth-layout';
import { cn } from '@/lib/utils';
import type { RegisterPageProps } from '@/types/auth';
import { Link, useForm } from '@inertiajs/react';
import { useMemo, type FormEvent } from 'react';
import { scorePassword } from './password-strength';

interface RegisterForm {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
    company_name: string;
    terms: boolean;
    [key: string]: string | boolean;
}

const TONE_BAR: Record<'destructive' | 'warning' | 'success', string> = {
    destructive: 'bg-destructive',
    warning: 'bg-warning',
    success: 'bg-success',
};

const TONE_TEXT: Record<'destructive' | 'warning' | 'success', string> = {
    destructive: 'text-destructive',
    warning: 'text-warning',
    success: 'text-success',
};

/** The invitation flow lands here with the invited address in the query string. */
function invitedEmail(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return new URLSearchParams(window.location.search).get('email') ?? '';
}

export default function Register({ socials }: RegisterPageProps) {
    const prefilledEmail = useMemo(invitedEmail, []);

    const form = useForm<RegisterForm>({
        first_name: '',
        last_name: '',
        email: prefilledEmail,
        password: '',
        password_confirmation: '',
        company_name: '',
        terms: false,
    });

    const { data, setData, post, processing, errors } = form;
    const strength = scorePassword(data.password);
    const mismatch = data.password_confirmation !== '' && data.password_confirmation !== data.password;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        post(route('register.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    const providers = socials.map((provider) => ({ provider, href: routeUrl('social.redirect', provider) })).filter((entry) => entry.href);

    return (
        <AuthLayout
            title="Create your account"
            description="Set up your workspace in under a minute."
            footer={
                <>
                    Already have an account?{' '}
                    <Link href={route('login')} className="font-medium text-foreground underline-offset-4 hover:underline">
                        Sign in
                    </Link>
                </>
            }
        >
            <form onSubmit={submit} noValidate className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="first_name">First name</Label>
                        <Input
                            id="first_name"
                            name="first_name"
                            value={data.first_name}
                            autoComplete="given-name"
                            autoFocus
                            required
                            aria-invalid={Boolean(errors.first_name)}
                            aria-describedby={errors.first_name ? 'first_name-error' : undefined}
                            onChange={(event) => setData('first_name', event.target.value)}
                        />
                        {errors.first_name && (
                            <p id="first_name-error" className="text-sm font-medium text-destructive">
                                {errors.first_name}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            name="last_name"
                            value={data.last_name}
                            autoComplete="family-name"
                            required
                            aria-invalid={Boolean(errors.last_name)}
                            aria-describedby={errors.last_name ? 'last_name-error' : undefined}
                            onChange={(event) => setData('last_name', event.target.value)}
                        />
                        {errors.last_name && (
                            <p id="last_name-error" className="text-sm font-medium text-destructive">
                                {errors.last_name}
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">Work email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
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
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        required
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={`password-strength${errors.password ? ' password-error' : ''}`}
                        onChange={(event) => setData('password', event.target.value)}
                    />

                    <div id="password-strength" aria-live="polite" className="grid gap-1.5">
                        <div className="flex gap-1" aria-hidden="true">
                            {[1, 2, 3, 4].map((step) => (
                                <span
                                    key={step}
                                    className={cn(
                                        'h-1 flex-1 rounded-full transition-colors',
                                        data.password !== '' && strength.score >= step ? TONE_BAR[strength.tone] : 'bg-muted',
                                    )}
                                />
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {data.password === '' ? (
                                'At least 12 characters, with a mix of cases, a number and a symbol.'
                            ) : (
                                <>
                                    <span className={cn('font-medium', TONE_TEXT[strength.tone])}>{strength.label}</span>
                                    {strength.hint ? ` — ${strength.hint}` : ''}
                                </>
                            )}
                        </p>
                    </div>

                    {errors.password && (
                        <p id="password-error" className="text-sm font-medium text-destructive">
                            {errors.password}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        required
                        aria-invalid={mismatch || Boolean(errors.password_confirmation)}
                        aria-describedby={mismatch || errors.password_confirmation ? 'password_confirmation-error' : undefined}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                    />
                    {(mismatch || errors.password_confirmation) && (
                        <p id="password_confirmation-error" className="text-sm font-medium text-destructive">
                            {errors.password_confirmation ?? 'Both passwords must match.'}
                        </p>
                    )}
                </div>

                <Separator />

                <div className="grid gap-2">
                    <Label htmlFor="company_name">Workspace name</Label>
                    <Input
                        id="company_name"
                        name="company_name"
                        value={data.company_name}
                        autoComplete="organization"
                        required
                        placeholder="Acme Inc."
                        aria-invalid={Boolean(errors.company_name)}
                        aria-describedby={`company_name-description${errors.company_name ? ' company_name-error' : ''}`}
                        onChange={(event) => setData('company_name', event.target.value)}
                    />
                    <p id="company_name-description" className="text-xs text-muted-foreground">
                        You can invite teammates and rename this later.
                    </p>
                    {errors.company_name && (
                        <p id="company_name-error" className="text-sm font-medium text-destructive">
                            {errors.company_name}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="terms"
                            checked={data.terms}
                            aria-invalid={Boolean(errors.terms)}
                            aria-describedby={errors.terms ? 'terms-error' : undefined}
                            className="mt-0.5"
                            onCheckedChange={(checked) => setData('terms', checked === true)}
                        />
                        <Label htmlFor="terms" className="text-sm font-normal">
                            I agree to the terms of service and the privacy policy.
                        </Label>
                    </div>
                    {errors.terms && (
                        <p id="terms-error" className="text-sm font-medium text-destructive">
                            {errors.terms}
                        </p>
                    )}
                </div>

                <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                    Create account
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
                                <a href={href ?? '#'}>Continue with {provider.charAt(0).toUpperCase() + provider.slice(1)}</a>
                            </Button>
                        ))}
                    </div>
                </>
            )}
        </AuthLayout>
    );
}
