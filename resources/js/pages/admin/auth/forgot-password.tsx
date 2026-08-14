import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminAuthLayout } from '@/layouts/admin-auth-layout';
import { Link, useForm } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import type { FormEvent } from 'react';

interface ForgotPasswordProps {
    status?: string | null;
}

export default function AdminForgotPassword({ status }: ForgotPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        post(route('admin.password.email'));
    }

    return (
        <AdminAuthLayout
            title="Forgot password"
            description="We'll email you a link to reset your console password."
            footer={
                <Link href={route('admin.login')} className="font-medium text-foreground underline-offset-4 hover:underline">
                    Back to sign in
                </Link>
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
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.email)}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email && <p className="text-sm font-medium text-destructive">{errors.email}</p>}
                </div>

                <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                    Email reset link
                </Button>
            </form>
        </AdminAuthLayout>
    );
}
