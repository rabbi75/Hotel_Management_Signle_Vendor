import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PublicMenuNode } from '@/types/cms';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PublicShell } from '@/pages/cms/public-shell';

interface Props {
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

export default function AccountLogin({ menus }: Props) {
    const form = useForm({ email: '', password: '', remember: false });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post(route('account.login.store'), { onFinish: () => form.reset('password') });
    }

    return (
        <PublicShell header={menus.header} footer={menus.footer}>
            <Head title="Sign in" />
            <div className="mx-auto w-full max-w-md px-4 py-16 sm:px-6">
                <h1 className="text-3xl font-semibold tracking-tight">Sign in</h1>
                <p className="mt-2 text-muted-foreground">Access your bookings, receipts, and guest profile.</p>

                <form onSubmit={submit} className="mt-8 space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="space-y-1.5">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            aria-invalid={Boolean(form.errors.email)}
                        />
                        {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                    </div>
                    <Button type="submit" className="w-full" disabled={form.processing}>
                        Sign in
                    </Button>
                </form>

                <p className="mt-6 text-sm text-muted-foreground">
                    New guest?{' '}
                    <Link href={route('account.register')} className="underline underline-offset-4">
                        Create an account
                    </Link>
                </p>
            </div>
        </PublicShell>
    );
}
