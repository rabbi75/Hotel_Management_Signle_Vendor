import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AuthLayout } from '@/layouts/auth-layout';
import { useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';

interface ConfirmPasswordForm {
    password: string;
    [key: string]: string;
}

export default function ConfirmPassword() {
    const form = useForm<ConfirmPasswordForm>({ password: '' });
    const { data, setData, post, processing, errors } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        post(route('password.confirm.store'), { onFinish: () => form.reset('password') });
    }

    return (
        <AuthLayout title="Confirm your password" description="This is a secure area. Please confirm your password before continuing.">
            <div className="mb-6 flex items-center justify-center">
                <span className="flex size-14 items-center justify-center rounded-full bg-muted">
                    <ShieldCheck className="size-7 text-muted-foreground" aria-hidden="true" />
                </span>
            </div>

            <form onSubmit={submit} noValidate className="space-y-5">
                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    {errors.password && (
                        <p id="password-error" className="text-sm font-medium text-destructive">
                            {errors.password}
                        </p>
                    )}
                </div>

                <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                    Confirm
                </Button>
            </form>
        </AuthLayout>
    );
}
