import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AuthLayout } from '@/layouts/auth-layout';
import { cn } from '@/lib/utils';
import type { ResetPasswordPageProps } from '@/types/auth';
import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { scorePassword } from './password-strength';

interface ResetPasswordForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
    [key: string]: string;
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

export default function ResetPassword({ email, token }: ResetPasswordPageProps) {
    const form = useForm<ResetPasswordForm>({ token, email, password: '', password_confirmation: '' });
    const { data, setData, post, processing, errors } = form;

    const strength = scorePassword(data.password);
    const mismatch = data.password_confirmation !== '' && data.password_confirmation !== data.password;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <AuthLayout
            title="Choose a new password"
            description="Pick something you haven't used before."
            footer={
                <Link href={route('login')} className="font-medium text-foreground underline-offset-4 hover:underline">
                    Back to sign in
                </Link>
            }
        >
            <form onSubmit={submit} noValidate className="space-y-5">
                <input type="hidden" name="token" value={data.token} />

                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        readOnly={email !== ''}
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
                    <Label htmlFor="password">New password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
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
                    <Label htmlFor="password_confirmation">Confirm new password</Label>
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

                <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                    Reset password
                </Button>
            </form>
        </AuthLayout>
    );
}
