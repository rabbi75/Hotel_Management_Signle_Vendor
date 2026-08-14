import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminAuthLayout } from '@/layouts/admin-auth-layout';
import { Link, useForm } from '@inertiajs/react';
import { CircleCheck, Eye, EyeOff } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface AdminLoginProps {
    status?: string | null;
}

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
    [key: string]: string | boolean;
}

export default function AdminLogin({ status }: AdminLoginProps) {
    const [revealed, setRevealed] = useState(false);
    const form = useForm<LoginForm>({ email: '', password: '', remember: false });
    const { data, setData, post, processing, errors } = form;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        post(route('admin.login'), { onFinish: () => form.reset('password') });
    }

    return (
        <AdminAuthLayout title="Sign in" description="Enter your operator credentials to continue.">
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
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email && <p className="text-sm font-medium text-destructive">{errors.email}</p>}
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor="password">Password</Label>
                        <Link
                            href={route('admin.password.request')}
                            className="rounded-sm text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            Forgot password?
                        </Link>
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
                    {errors.password && <p className="text-sm font-medium text-destructive">{errors.password}</p>}
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
        </AdminAuthLayout>
    );
}
