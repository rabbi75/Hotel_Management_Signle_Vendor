import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { BooleanCell, RelativeDateCell } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { UserAbilities, UserLoginEntry, UserRow } from '@/types/users';
import { Link, router, useForm } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import {
    Building2,
    History,
    KeyRound,
    Pencil,
    RotateCcw,
    ScrollText,
    ShieldCheck,
    ShieldOff,
    Trash2,
    TriangleAlert,
    VenetianMask,
} from 'lucide-react';
import { useState } from 'react';

interface UsersShowProps {
    user: UserRow;
    login_history: UserLoginEntry[];
    can: UserAbilities;
}

const BADGE_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    success: 'success',
    info: 'info',
    warning: 'warning',
    danger: 'destructive',
    primary: 'default',
    neutral: 'secondary',
};

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PPpp') : '—';
}

export default function UsersShow({ user, login_history: loginHistory, can }: UsersShowProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const [suspendOpen, setSuspendOpen] = useState(false);
    const suspendForm = useForm<{ reason: string }>({ reason: '' });
    const reasonTooShort = suspendForm.data.reason.trim().length < 5;

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Users', href: route('users.index') },
        { label: user.name },
    ];

    const roles = user.roles ?? [];
    const companies = user.companies ?? [];

    // RoleResource only carries permissions when the controller eager-loaded
    // them, so an empty set means "not sent", not "none granted".
    const effectivePermissions = [...new Set(roles.flatMap((role) => role.permissions?.map((permission) => permission.name) ?? []))].sort();
    const permissionsKnown = roles.some((role) => role.permissions !== undefined);

    const activityUrl = routeUrl('audit.activity.index');

    async function remove(): Promise<void> {
        const ok = await confirm({
            title: `Delete ${user.name}?`,
            description: 'The account is soft-deleted and can be restored by an administrator.',
            variant: 'destructive',
            confirmWord: 'delete',
            confirmLabel: 'Delete user',
        });

        if (ok) {
            router.delete(route('users.destroy', user.id));
        }
    }

    async function impersonate(): Promise<void> {
        const ok = await confirm({
            title: `Sign in as ${user.name}?`,
            description: 'Everything you do will be attributed to this account and recorded in the security log.',
        });

        if (ok) {
            router.post(route('users.impersonate', user.id));
        }
    }

    return (
        <AppLayout title={user.name} breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title={user.name}
                    description={user.job_title ?? user.email}
                    actions={
                        <>
                            {can.update && allows('users.update') && (
                                <Button asChild variant="outline">
                                    <Link href={route('users.edit', user.id)}>
                                        <Pencil className="size-4" aria-hidden="true" />
                                        Edit
                                    </Link>
                                </Button>
                            )}

                            {can.suspend &&
                                allows('users.suspend') &&
                                (user.status.value === 'suspended' ? (
                                    <Button variant="outline" onClick={() => router.post(route('users.restore', user.id))}>
                                        <RotateCcw className="size-4" aria-hidden="true" />
                                        Restore
                                    </Button>
                                ) : (
                                    <Button variant="outline" onClick={() => setSuspendOpen(true)}>
                                        <ShieldOff className="size-4" aria-hidden="true" />
                                        Suspend
                                    </Button>
                                ))}

                            {can.impersonate && allows('users.impersonate') && !user.is_super_admin && (
                                <Button variant="outline" onClick={() => void impersonate()}>
                                    <VenetianMask className="size-4" aria-hidden="true" />
                                    Impersonate
                                </Button>
                            )}

                            {can.delete && allows('users.delete') && (
                                <Button variant="destructive" onClick={() => void remove()}>
                                    <Trash2 className="size-4" aria-hidden="true" />
                                    Delete
                                </Button>
                            )}
                        </>
                    }
                />

                {user.status.value === 'suspended' && (
                    <Alert variant="warning">
                        <TriangleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>This account is suspended</AlertTitle>
                        <AlertDescription>
                            {user.suspended_reason ?? 'No reason was recorded.'}
                            {user.suspended_at && <> · {absolute(user.suspended_at)}</>}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Profile</CardTitle>
                            <CardDescription>Account details and security posture.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="flex items-center gap-3">
                                <Avatar size="lg">
                                    {user.avatar_url && <AvatarImage src={user.avatar_url} alt="" />}
                                    <AvatarFallback>{user.initials}</AvatarFallback>
                                </Avatar>
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{user.name}</p>
                                    <p className="truncate text-sm text-muted-foreground">{user.email}</p>
                                </div>
                            </div>

                            <Separator />

                            <dl className="grid gap-3 text-sm">
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Status</dt>
                                    <dd>
                                        <Badge variant={BADGE_VARIANT[user.status.color] ?? 'secondary'}>{user.status.label}</Badge>
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Email verified</dt>
                                    <dd>
                                        <BooleanCell value={user.email_verified} />
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Two-factor</dt>
                                    <dd>
                                        <BooleanCell value={user.two_factor_enabled} trueLabel="Enabled" falseLabel="Disabled" />
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Phone</dt>
                                    <dd className="truncate">{user.phone ?? '—'}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Timezone</dt>
                                    <dd className="truncate">{user.timezone}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Language</dt>
                                    <dd className="uppercase">{user.locale}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Last login</dt>
                                    <dd>
                                        <RelativeDateCell value={user.last_login_at} />
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Registered</dt>
                                    <dd>{absolute(user.created_at)}</dd>
                                </div>
                            </dl>

                            {user.bio && (
                                <>
                                    <Separator />
                                    <p className="text-sm text-muted-foreground">{user.bio}</p>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="size-4 text-muted-foreground" aria-hidden="true" />
                                    Workspace memberships
                                </CardTitle>
                                <CardDescription>Every workspace this account belongs to.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {companies.length === 0 ? (
                                    <EmptyState
                                        icon={Building2}
                                        title="No memberships"
                                        description="This account is not part of any workspace."
                                    />
                                ) : (
                                    <ul className="divide-y divide-border">
                                        {companies.map((company) => (
                                            <li
                                                key={company.id}
                                                className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                            >
                                                <span className="min-w-0 truncate text-sm font-medium">{company.name}</span>
                                                <Badge variant="outline">{company.role ?? 'member'}</Badge>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldCheck className="size-4 text-muted-foreground" aria-hidden="true" />
                                    Roles and effective permissions
                                </CardTitle>
                                <CardDescription>
                                    {user.is_super_admin
                                        ? 'This account is a super admin and bypasses every permission check.'
                                        : 'Permissions are the union of every assigned role.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {roles.length === 0 ? (
                                    <EmptyState
                                        icon={KeyRound}
                                        title="No roles assigned"
                                        description="This account has no RBAC role in this workspace."
                                    />
                                ) : (
                                    <div className="flex flex-wrap gap-2">
                                        {roles.map((role) => (
                                            <Badge key={role.id} variant={role.is_super_admin ? 'warning' : 'secondary'}>
                                                {role.label}
                                            </Badge>
                                        ))}
                                    </div>
                                )}

                                {roles.length > 0 && (
                                    <>
                                        <Separator />
                                        {effectivePermissions.length > 0 ? (
                                            <ul className="flex flex-wrap gap-1.5">
                                                {effectivePermissions.map((permission) => (
                                                    <li key={permission}>
                                                        <Badge variant="outline" className="font-mono text-[11px]">
                                                            {permission}
                                                        </Badge>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                {permissionsKnown
                                                    ? 'These roles grant no permissions.'
                                                    : 'Open the role to see the permissions it grants.'}
                                            </p>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <History className="size-4 text-muted-foreground" aria-hidden="true" />
                                    Login history
                                </CardTitle>
                                <CardDescription>The ten most recent authentication attempts.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {loginHistory.length === 0 ? (
                                    <EmptyState
                                        icon={History}
                                        title="No sign-ins recorded"
                                        description="Attempts appear here as soon as they happen."
                                    />
                                ) : (
                                    <ul className="divide-y divide-border">
                                        {loginHistory.map((entry) => (
                                            <li
                                                key={entry.id}
                                                className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2.5 first:pt-0 last:pb-0"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">
                                                        {entry.browser ?? 'Unknown browser'}
                                                        {entry.platform && (
                                                            <span className="text-muted-foreground"> · {entry.platform}</span>
                                                        )}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {entry.ip_address ?? 'IP not recorded'}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <Badge variant={entry.successful ? 'success' : 'destructive'}>
                                                        {entry.successful ? 'Success' : 'Failed'}
                                                    </Badge>
                                                    <RelativeDateCell value={entry.logged_in_at} />
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ScrollText className="size-4 text-muted-foreground" aria-hidden="true" />
                                    Activity
                                </CardTitle>
                                <CardDescription>Model changes this account has made.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <EmptyState
                                    icon={ScrollText}
                                    title="Activity lives in the audit log"
                                    description="The full, filterable trail is kept in one place so it can be searched and exported."
                                    action={
                                        activityUrl && allows('audit.activity.view') ? (
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={`${activityUrl}?activity_filters[causer_id]=${user.id}`}>
                                                    View this user’s activity
                                                </Link>
                                            </Button>
                                        ) : null
                                    }
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Dialog
                open={suspendOpen}
                onOpenChange={(next) => {
                    setSuspendOpen(next);

                    if (!next) {
                        suspendForm.reset();
                        suspendForm.clearErrors();
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Suspend {user.name}</DialogTitle>
                        <DialogDescription>
                            A suspended account cannot sign in. The reason is stored on the account and written to the security log.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="suspend-reason">
                            Reason
                            <span className="text-destructive" aria-hidden="true">
                                *
                            </span>
                        </Label>
                        <Textarea
                            id="suspend-reason"
                            rows={3}
                            value={suspendForm.data.reason}
                            onChange={(event) => suspendForm.setData('reason', event.target.value)}
                            aria-invalid={suspendForm.errors.reason ? true : undefined}
                            aria-describedby={suspendForm.errors.reason ? 'suspend-reason-error' : 'suspend-reason-hint'}
                        />
                        {suspendForm.errors.reason ? (
                            <p id="suspend-reason-error" className="text-sm text-destructive">
                                {suspendForm.errors.reason}
                            </p>
                        ) : (
                            <p id="suspend-reason-hint" className="text-xs text-muted-foreground">
                                At least 5 characters.
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setSuspendOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            loading={suspendForm.processing}
                            disabled={reasonTooShort}
                            onClick={() =>
                                suspendForm.post(route('users.suspend', user.id), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setSuspendOpen(false);
                                        suspendForm.reset();
                                    },
                                })
                            }
                        >
                            Suspend
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
