import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminAuthLayout } from '@/layouts/admin-auth-layout';
import { useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface ConfirmPasswordProps {
    intended?: string | null;
}

interface ConfirmForm {
    password: string;
    intended: string;
    [key: string]: string;
}

/**
 * The re-challenge in front of the credential panels.
 *
 * Asked for even though the operator is already signed in: a session left open
 * on an unlocked machine should not be enough to read the installation's
 * third-party secrets or rewrite its password policy.
 */
export default function AdminConfirmPassword({ intended }: ConfirmPasswordProps) {
    const [revealed, setRevealed] = useState(false);
    const form = useForm<ConfirmForm>({ password: '', intended: intended ?? '' });
    const { data, setData, post, processing, errors } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        post(route('admin.password.confirm.store'), { onFinish: () => form.reset('password') });
    }

    return (
        <AdminAuthLayout
            title="Confirm your password"
            description="This area holds the installation's credentials, so we ask again before opening it."
        >
            <form onSubmit={submit} noValidate className="space-y-5">
                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <div className="relative">
                        <Input
                            id="password"
                            type={revealed ? 'text' : 'password'}
                            value={data.password}
                            autoComplete="current-password"
                            autoFocus
                            required
                            aria-invalid={errors.password ? true : undefined}
                            aria-describedby={errors.password ? 'password-error' : undefined}
                            onChange={(event) => setData('password', event.target.value)}
                        />
                        <button
                            type="button"
                            onClick={() => setRevealed((current) => !current)}
                            aria-label={revealed ? 'Hide password' : 'Show password'}
                            className="absolute inset-y-0 right-0 flex items-center rounded-md px-3 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {revealed ? <EyeOff className="size-4" aria-hidden="true" /> : <Eye className="size-4" aria-hidden="true" />}
                        </button>
                    </div>
                    {errors.password && (
                        <p id="password-error" className="text-sm text-destructive">
                            {errors.password}
                        </p>
                    )}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? 'Confirming…' : 'Confirm'}
                </Button>
            </form>
        </AdminAuthLayout>
    );
}
