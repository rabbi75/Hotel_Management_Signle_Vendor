import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminAuthLayout } from '@/layouts/admin-auth-layout';
import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

interface ChallengeForm {
    code: string;
    recovery_code: string;
    [key: string]: string;
}

export default function AdminTwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const form = useForm<ChallengeForm>({ code: '', recovery_code: '' });
    const { data, setData, post, processing, errors } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        post(route('admin.two-factor.challenge'));
    }

    return (
        <AdminAuthLayout
            title="Two-factor authentication"
            description={
                useRecovery
                    ? 'Enter one of your recovery codes to continue.'
                    : 'Enter the code from your authenticator app to continue.'
            }
        >
            <form onSubmit={submit} noValidate className="space-y-5">
                {useRecovery ? (
                    <div className="grid gap-2">
                        <Label htmlFor="recovery_code">Recovery code</Label>
                        <Input
                            id="recovery_code"
                            value={data.recovery_code}
                            autoComplete="one-time-code"
                            autoFocus
                            required
                            onChange={(event) => setData('recovery_code', event.target.value)}
                        />
                        {errors.recovery_code && <p className="text-sm font-medium text-destructive">{errors.recovery_code}</p>}
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="code">Authentication code</Label>
                        <Input
                            id="code"
                            inputMode="numeric"
                            value={data.code}
                            autoComplete="one-time-code"
                            autoFocus
                            required
                            onChange={(event) => setData('code', event.target.value)}
                        />
                        {errors.code && <p className="text-sm font-medium text-destructive">{errors.code}</p>}
                    </div>
                )}

                <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                    Verify
                </Button>

                <button
                    type="button"
                    className="w-full text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    onClick={() => setUseRecovery((current) => !current)}
                >
                    {useRecovery ? 'Use an authenticator code instead' : 'Use a recovery code instead'}
                </button>
            </form>
        </AdminAuthLayout>
    );
}
