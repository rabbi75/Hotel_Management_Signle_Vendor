import { PageHeader } from '@/components/app-shell/page-header';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { AvatarCell, RelativeDateCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AdminLayout } from '@/layouts/admin-layout';
import type { TablePayload } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

interface AdminRow {
    id: number;
    uuid: string;
    name: string;
    email: string;
    initials: string;
    avatar: string | null;
    status: string;
    is_super_admin: boolean;
    roles: string[];
    two_factor_enabled: boolean;
    last_login_at: string | null;
}

interface AdminsIndexProps {
    table: TablePayload<AdminRow>;
    roles: Record<string, string>;
}

interface AdminForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    [key: string]: string;
}

function emptyForm(role: string): AdminForm {
    return { name: '', email: '', password: '', password_confirmation: '', role };
}

export default function AdminsIndex({ table, roles }: AdminsIndexProps) {
    const confirm = useConfirm();
    const roleKeys = Object.keys(roles);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<AdminRow | null>(null);
    const form = useForm<AdminForm>(emptyForm(roleKeys[0] ?? 'support'));

    function openCreate(): void {
        setEditing(null);
        form.setData(emptyForm(roleKeys[0] ?? 'support'));
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(admin: AdminRow): void {
        setEditing(admin);
        form.clearErrors();
        form.setData({
            name: admin.name,
            email: admin.email,
            password: '',
            password_confirmation: '',
            role: admin.roles[0] ?? roleKeys[0] ?? 'support',
        });
        setOpen(true);
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        editing ? form.put(route('admin.admins.update', editing.id), options) : form.post(route('admin.admins.store'), options);
    }

    async function toggle(admin: AdminRow): Promise<void> {
        const deactivating = admin.status === 'active';
        const ok = await confirm({
            title: deactivating ? `Deactivate ${admin.name}?` : `Reactivate ${admin.name}?`,
            description: deactivating ? 'They will be signed out and unable to reach the console.' : 'They regain console access.',
            confirmLabel: deactivating ? 'Deactivate' : 'Reactivate',
            variant: deactivating ? 'destructive' : 'default',
        });

        if (!ok) {
            return;
        }

        router.patch(
            route(deactivating ? 'admin.admins.deactivate' : 'admin.admins.reactivate', admin.id),
            {},
            { preserveScroll: true },
        );
    }

    const columns: ColumnRenderers<AdminRow> = {
        name: (row) => <AvatarCell name={row.name} subtitle={row.email} src={row.avatar} initials={row.initials} />,
        roles: (row) => (
            <span className="flex gap-1">
                {row.roles.map((role) => (
                    <Badge key={role} variant={role === 'super-admin' ? 'default' : 'secondary'}>
                        {role}
                    </Badge>
                ))}
            </span>
        ),
        status: (row) =>
            row.status === 'active' ? <Badge variant="success">Active</Badge> : <Badge variant="destructive">Suspended</Badge>,
        two_factor_enabled: (row) => (row.two_factor_enabled ? <Badge variant="success">On</Badge> : <Badge variant="outline">Off</Badge>),
        last_login_at: (row) => <RelativeDateCell value={row.last_login_at} />,
    };

    function rowActions(row: AdminRow): RowAction[] {
        return [
            { id: 'edit', label: 'Edit', icon: <Pencil className="size-4" />, onSelect: () => openEdit(row) },
            {
                id: 'toggle',
                label: row.status === 'active' ? 'Deactivate' : 'Reactivate',
                icon: <Power className="size-4" />,
                destructive: row.status === 'active',
                separatorBefore: true,
                onSelect: () => void toggle(row),
            },
        ];
    }

    return (
        <AdminLayout title="Admins" breadcrumbs={[{ label: 'Platform' }, { label: 'Admins' }]}>
            <div className="space-y-6">
                <PageHeader
                    title="Admins"
                    description="Everyone with access to the operator console."
                    actions={
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            New admin
                        </Button>
                    }
                />

                <DataTable
                    payload={table}
                    propKey="table"
                    columns={columns}
                    rowActions={rowActions}
                    searchPlaceholder="Search admins…"
                    emptyState={<EmptyState icon={ShieldCheck} title="No admins" description="Create the first operator." />}
                />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit admin' : 'New admin'}</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="admin-name">Name</Label>
                            <Input id="admin-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="admin-email">Email</Label>
                            <Input
                                id="admin-email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                required
                            />
                            {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="admin-role">Role</Label>
                            <Select value={form.data.role} onValueChange={(v) => form.setData('role', v)}>
                                <SelectTrigger id="admin-role">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(roles).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="admin-password">{editing ? 'New password (optional)' : 'Password'}</Label>
                            <Input
                                id="admin-password"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                required={!editing}
                            />
                            {form.errors.password && <p className="text-sm text-destructive">{form.errors.password}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="admin-password-confirm">Confirm password</Label>
                            <Input
                                id="admin-password-confirm"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password_confirmation}
                                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                required={!editing}
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                {editing ? 'Save' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
