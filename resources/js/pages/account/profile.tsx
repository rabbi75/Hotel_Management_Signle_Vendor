import { AccountShell } from '@/components/public/account-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PublicMenuNode } from '@/types/cms';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    customer: {
        name: string;
        first_name: string;
        last_name: string;
        email: string;
        phone: string | null;
    };
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
}

export default function AccountProfile({ customer, menus }: Props) {
    const form = useForm({
        first_name: customer.first_name,
        last_name: customer.last_name,
        email: customer.email,
        phone: customer.phone ?? '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put(route('account.profile.update'), { onSuccess: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <AccountShell title="Profile" description="Keep your contact details current for confirmations." customerName={customer.name} menus={menus}>
            <Head title="Profile" />

            <form onSubmit={submit} className="max-w-xl space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm">
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
                    <Label htmlFor="password">New password</Label>
                    <Input id="password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="password_confirmation">Confirm new password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={form.data.password_confirmation}
                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    />
                </div>
                <Button type="submit" disabled={form.processing}>
                    Save profile
                </Button>
            </form>
        </AccountShell>
    );
}
