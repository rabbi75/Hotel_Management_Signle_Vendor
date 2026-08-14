import { PageHeader } from '@/components/app-shell/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AdminLayout } from '@/layouts/admin-layout';
import type { AdminSummary } from '@/types';
import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface TwoFactorState {
    enabled: boolean;
    confirmed: boolean;
    pending: boolean;
    qrCodeSvg: string | null;
    secret: string | null;
}

interface AccountProps {
    admin: AdminSummary;
    two_factor: TwoFactorState;
}

export default function AdminAccount({ admin, two_factor }: AccountProps) {
    const profile = useForm({ name: admin.name, email: admin.email });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const confirm = useForm({ code: '' });

    function saveProfile(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        profile.put(route('admin.account.update'), { preserveScroll: true });
    }

    function savePassword(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        password.put(route('admin.account.password'), {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    }

    function enable2fa(): void {
        router.post(route('admin.account.two-factor.enable'), {}, { preserveScroll: true });
    }

    function confirm2fa(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        confirm.post(route('admin.account.two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirm.reset(),
        });
    }

    function disable2fa(): void {
        router.delete(route('admin.account.two-factor.disable'), { preserveScroll: true });
    }

    return (
        <AdminLayout title="Account" breadcrumbs={[{ label: 'Platform' }, { label: 'Account' }]}>
            <div className="mx-auto w-full max-w-2xl space-y-6">
                <PageHeader title="Account & security" description="Your operator profile, password, and two-factor authentication." />

                <Card>
                    <CardHeader>
                        <CardTitle>Profile</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={saveProfile} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} />
                                {profile.errors.name && <p className="text-sm text-destructive">{profile.errors.name}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={profile.data.email} onChange={(e) => profile.setData('email', e.target.value)} />
                                {profile.errors.email && <p className="text-sm text-destructive">{profile.errors.email}</p>}
                            </div>
                            <Button type="submit" loading={profile.processing}>
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Password</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={savePassword} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">Current password</Label>
                                <Input
                                    id="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={password.data.current_password}
                                    onChange={(e) => password.setData('current_password', e.target.value)}
                                />
                                {password.errors.current_password && <p className="text-sm text-destructive">{password.errors.current_password}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={password.data.password}
                                    onChange={(e) => password.setData('password', e.target.value)}
                                />
                                {password.errors.password && <p className="text-sm text-destructive">{password.errors.password}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirm new password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={password.data.password_confirmation}
                                    onChange={(e) => password.setData('password_confirmation', e.target.value)}
                                />
                            </div>
                            <Button type="submit" loading={password.processing}>
                                Update password
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Two-factor authentication</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {two_factor.confirmed ? (
                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">Two-factor authentication is active on your account.</p>
                                <Button variant="destructive" onClick={disable2fa}>
                                    Disable
                                </Button>
                            </div>
                        ) : two_factor.pending && two_factor.qrCodeSvg ? (
                            <div className="space-y-4">
                                <p className="text-sm text-muted-foreground">Scan this with your authenticator app, then enter a code to confirm.</p>
                                <div className="inline-block rounded-md bg-white p-3" dangerouslySetInnerHTML={{ __html: two_factor.qrCodeSvg }} />
                                {two_factor.secret && (
                                    <p className="text-xs text-muted-foreground">
                                        Setup key: <code className="font-mono">{two_factor.secret}</code>
                                    </p>
                                )}
                                <form onSubmit={confirm2fa} className="flex items-end gap-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="code">Code</Label>
                                        <Input
                                            id="code"
                                            inputMode="numeric"
                                            value={confirm.data.code}
                                            onChange={(e) => confirm.setData('code', e.target.value)}
                                        />
                                    </div>
                                    <Button type="submit" loading={confirm.processing}>
                                        Confirm
                                    </Button>
                                </form>
                                {confirm.errors.code && <p className="text-sm text-destructive">{confirm.errors.code}</p>}
                            </div>
                        ) : (
                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">Add a second factor to protect your operator account.</p>
                                <Button onClick={enable2fa}>Enable two-factor</Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
