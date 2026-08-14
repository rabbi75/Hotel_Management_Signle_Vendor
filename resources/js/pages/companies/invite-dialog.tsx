import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { CompanyRoleOption } from '@/types/companies';
import { useForm } from '@inertiajs/react';

interface InviteFormValues {
    email: string;
    role: string;
    permission_roles: string[];
    [key: string]: string | string[];
}

export interface InviteDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: CompanyRoleOption[];
    permissionRoles: string[];
}

/** Sends a workspace invitation. Owner is never offered — that is a transfer. */
export function InviteDialog({ open, onOpenChange, roles, permissionRoles }: InviteDialogProps) {
    const assignable = roles.filter((role) => role.value !== 'owner');

    const form = useForm<InviteFormValues>({
        email: '',
        role: assignable[assignable.length - 1]?.value ?? 'member',
        permission_roles: [],
    });

    function toggle(name: string, checked: boolean): void {
        form.setData(
            'permission_roles',
            checked ? [...new Set([...form.data.permission_roles, name])] : form.data.permission_roles.filter((entry) => entry !== name),
        );
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);

                if (!next) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('companies.invitations.store'), {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                onOpenChange(false);
                            },
                        });
                    }}
                    className="space-y-5"
                >
                    <DialogHeader>
                        <DialogTitle>Invite a member</DialogTitle>
                        <DialogDescription>They receive an email with a link that expires.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="invite-email">
                            Email address
                            <span className="text-destructive" aria-hidden="true">
                                *
                            </span>
                        </Label>
                        <Input
                            id="invite-email"
                            type="email"
                            required
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            aria-invalid={form.errors.email ? true : undefined}
                            aria-describedby={form.errors.email ? 'invite-email-error' : undefined}
                        />
                        {form.errors.email && (
                            <p id="invite-email-error" className="text-sm text-destructive">
                                {form.errors.email}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="invite-role">Workspace role</Label>
                        <Select value={form.data.role} onValueChange={(value) => form.setData('role', value)}>
                            <SelectTrigger id="invite-role" aria-invalid={form.errors.role ? true : undefined}>
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                {assignable.map((role) => (
                                    <SelectItem key={role.value} value={role.value}>
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.role && <p className="text-sm text-destructive">{form.errors.role}</p>}
                    </div>

                    {permissionRoles.length > 0 && (
                        <fieldset className="space-y-2">
                            <legend className="text-sm font-medium">Permission roles</legend>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {permissionRoles.map((name) => {
                                    const id = `invite-permission-${name}`;

                                    return (
                                        <div key={name} className="flex items-center gap-2">
                                            <Checkbox
                                                id={id}
                                                checked={form.data.permission_roles.includes(name)}
                                                onCheckedChange={(checked) => toggle(name, checked === true)}
                                            />
                                            <Label htmlFor={id} className="font-normal">
                                                {name}
                                            </Label>
                                        </div>
                                    );
                                })}
                            </div>
                            {form.errors.permission_roles && <p className="text-sm text-destructive">{form.errors.permission_roles}</p>}
                        </fieldset>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            Send invitation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
