import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PublicMenuNode } from '@/types/cms';
import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { PublicShell } from '@/pages/cms/public-shell';

interface Props {
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-sm text-destructive">{message}</p>;
}

export default function AccountRegister({ menus }: Props) {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post(route('account.register.store'), {
            onSuccess: () => form.reset('password', 'password_confirmation'),
        });
    }

    const firstError = Object.values(form.errors)[0];

    return (
        <PublicShell header={menus.header} footer={menus.footer}>
            <Head title="Create account" />
            <div className="mx-auto w-full max-w-md px-4 py-16 sm:px-6">
                <h1 className="text-3xl font-semibold tracking-tight">Create a guest account</h1>
                <p className="mt-2 text-muted-foreground">Save stays, track payment status, and manage your details.</p>

                <form onSubmit={submit} className="mt-8 space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm">
                    {firstError && (
                        <Alert variant="destructive">
                            <CircleAlert />
                            <AlertTitle>Account could not be created</AlertTitle>
                            <AlertDescription>{firstError}</AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="first_name">First name</Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                autoComplete="given-name"
                                required
                                value={form.data.first_name}
                                onChange={(event) => form.setData('first_name', event.target.value)}
                                aria-invalid={Boolean(form.errors.first_name)}
                            />
                            <FieldError message={form.errors.first_name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                autoComplete="family-name"
                                required
                                value={form.data.last_name}
                                onChange={(event) => form.setData('last_name', event.target.value)}
                                aria-invalid={Boolean(form.errors.last_name)}
                            />
                            <FieldError message={form.errors.last_name} />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="email"
                            required
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            aria-invalid={Boolean(form.errors.email)}
                        />
                        <FieldError message={form.errors.email} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="phone">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            autoComplete="tel"
                            value={form.data.phone}
                            onChange={(event) => form.setData('phone', event.target.value)}
                        />
                        <FieldError message={form.errors.phone} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            required
                            minLength={8}
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            aria-invalid={Boolean(form.errors.password)}
                        />
                        <p className="text-xs text-muted-foreground">At least 8 characters.</p>
                        <FieldError message={form.errors.password} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            minLength={8}
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                            aria-invalid={Boolean(form.errors.password_confirmation)}
                        />
                        <FieldError message={form.errors.password_confirmation} />
                    </div>
                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing ? 'Creating account…' : 'Create account'}
                    </Button>
                </form>

                <p className="mt-6 text-sm text-muted-foreground">
                    Already registered?{' '}
                    <Link href={route('account.login')} className="underline underline-offset-4">
                        Sign in
                    </Link>
                </p>
            </div>
        </PublicShell>
    );
}
