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
        form.post(route('account.register.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <PublicShell header={menus.header} footer={menus.footer}>
            <Head title="Create account" />
            <div className="mx-auto w-full max-w-md px-4 py-16 sm:px-6">
                <h1 className="text-3xl font-semibold tracking-tight">Create a guest account</h1>
                <p className="mt-2 text-muted-foreground">Save stays, track payment status, and manage your details.</p>

                <form onSubmit={submit} className="mt-8 space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="first_name">First name</Label>
                            <Input id="first_name" required value={form.data.first_name} onChange={(event) => form.setData('first_name', event.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="last_name">Last name</Label>
                            <Input id="last_name" required value={form.data.last_name} onChange={(event) => form.setData('last_name', event.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" required value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                        {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="phone">Phone</Label>
                        <Input id="phone" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="password">Password</Label>
                        <Input id="password" type="password" required value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        />
                    </div>
                    <Button type="submit" className="w-full" disabled={form.processing}>
                        Create account
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
