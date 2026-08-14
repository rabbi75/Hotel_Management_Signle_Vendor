import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminAuthLayout } from '@/layouts/admin-auth-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface ResetPasswordProps {
    email: string;
    token: string;
}

interface ResetForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
    [key: string]: string;
}

export default function AdminResetPassword({ email, token }: ResetPasswordProps) {
    const form = useForm<ResetForm>({ token, email, password: '', password_confirmation: '' });
    const { data, setData, post, processing, errors } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        post(route('admin.password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <AdminAuthLayout title="Reset password" description="Choose a new password for your console account.">
            <form onSubmit={submit} noValidate className="space-y-5">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input id="email" type="email" value={data.email} readOnly className="bg-muted" />
                    {errors.email && <p className="text-sm font-medium text-destructive">{errors.email}</p>}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">New password</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.password)}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    {errors.password && <p className="text-sm font-medium text-destructive">{errors.password}</p>}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        required
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                    />
                </div>

                <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                    Reset password
                </Button>
            </form>
        </AdminAuthLayout>
    );
}
