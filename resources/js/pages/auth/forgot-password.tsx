import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AuthLayout } from '@/layouts/auth-layout';
import type { ForgotPasswordPageProps } from '@/types/auth';
import { Link, useForm } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import type { FormEvent } from 'react';

interface ForgotPasswordForm {
    email: string;
    [key: string]: string;
}

export default function ForgotPassword({ status }: ForgotPasswordPageProps) {
    const { data, setData, post, processing, errors } = useForm<ForgotPasswordForm>({ email: '' });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        post(route('password.email'));
    }

    return (
        <AuthLayout
            title="Reset your password"
            description="Enter the address you signed up with and we'll email you a reset link."
            footer={
                <Link href={route('login')} className="font-medium text-foreground underline-offset-4 hover:underline">
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

                <Button type="submit" className="w-full" loading={processing} disabled={processing}>
                    Email password reset link
                </Button>
            </form>
        </AuthLayout>
    );
}
