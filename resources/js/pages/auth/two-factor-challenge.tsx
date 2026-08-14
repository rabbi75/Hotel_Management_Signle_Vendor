import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { AuthLayout } from '@/layouts/auth-layout';
import { Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

interface TwoFactorChallengeForm {
    code: string;
    recovery_code: string;
    [key: string]: string;
}

export default function TwoFactorChallenge() {
    const form = useForm<TwoFactorChallengeForm>({ code: '', recovery_code: '' });
    const { data, setData, post, processing, errors } = form;

    const [recovery, setRecovery] = useState(false);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        post(route('two-factor.login.store'), { onFinish: () => form.reset('code', 'recovery_code') });
    }

    /** Fortify picks whichever field is filled, so switching mode clears the other. */
    function toggleMode(): void {
        setRecovery((current) => {
            setData(current ? 'recovery_code' : 'code', '');

            return !current;
        });
    }

    return (
        <AuthLayout
            title="Two-factor authentication"
            description={
                recovery
                    ? 'Enter one of the recovery codes you saved when you enabled two-factor authentication.'
                    : 'Enter the 6-digit code from your authenticator app.'
            }
            footer={
                <Link href={route('login')} className="font-medium text-foreground underline-offset-4 hover:underline">
                    Back to sign in
                </Link>
            }
        >
            <form onSubmit={submit} noValidate className="space-y-6">
                {recovery ? (
                    <div className="grid gap-2">
                        <Label htmlFor="recovery_code">Recovery code</Label>
                        <Input
                            id="recovery_code"
                            name="recovery_code"
                            value={data.recovery_code}
                            autoComplete="one-time-code"
                            autoFocus
                            required
                            className="font-mono"
                            aria-invalid={Boolean(errors.recovery_code)}
                            aria-describedby={errors.recovery_code ? 'recovery_code-error' : undefined}
                            onChange={(event) => setData('recovery_code', event.target.value)}
                        />
                        {errors.recovery_code && (
                            <p id="recovery_code-error" className="text-sm font-medium text-destructive">
                                {errors.recovery_code}
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="code">Authentication code</Label>
                        <InputOTP
                            id="code"
                            name="code"
                            maxLength={6}
                            value={data.code}
                            autoFocus
                            containerClassName="justify-center"
                            aria-invalid={Boolean(errors.code)}
                            aria-describedby={errors.code ? 'code-error' : undefined}
                            onChange={(value) => setData('code', value)}
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
                            <p id="code-error" className="text-sm font-medium text-destructive">
                                {errors.code}
                            </p>
                        )}
                    </div>
                )}

                <Button
                    type="submit"
                    className="w-full"
                    loading={processing}
                    disabled={processing || (recovery ? data.recovery_code === '' : data.code.length < 6)}
                >
                    Verify
                </Button>

                <Button type="button" variant="ghost" className="w-full" onClick={toggleMode}>
                    {recovery ? 'Use an authenticator code instead' : 'Use a recovery code instead'}
                </Button>
            </form>
        </AuthLayout>
    );
}
