import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PermissionGroups, RoleSummary } from '@/types/roles';
import { Link } from '@inertiajs/react';
import { KeyRound, Lock, Pencil, ShieldCheck } from 'lucide-react';

interface RolesShowProps {
    role: RoleSummary;
    groups: PermissionGroups;
}

export default function RolesShow({ role, groups }: RolesShowProps) {
    const { can } = usePermissions();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Roles', href: route('roles.index') },
        { label: role.label },
    ];

    const granted = new Set((role.permissions ?? []).map((permission) => permission.name));

    return (
        <AppLayout title={role.label} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={role.label}
                    description={role.description ?? `Assigned to ${role.users_count ?? 0} account(s).`}
                    actions={
                        can('roles.update') && !role.is_super_admin ? (
                            <Button asChild>
                                <Link href={route('roles.edit', role.id)}>
                                    <Pencil className="size-4" aria-hidden="true" />
                                    Edit role
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                {role.is_super_admin && (
                    <Alert variant="warning">
                        <Lock className="size-4" aria-hidden="true" />
                        <AlertTitle>Super admin bypasses every check</AlertTitle>
                        <AlertDescription>
                            This role is granted by a gate, not by a permission list, and is deliberately not editable — it is the recovery
                            path if the matrix is ever misconfigured.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-muted-foreground" aria-hidden="true" />
                            Permissions
                        </CardTitle>
                        <CardDescription>
                            {role.is_super_admin ? 'Every permission, present and future.' : `${granted.size} permission(s) granted.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {!role.is_super_admin && granted.size === 0 ? (
                            <EmptyState icon={KeyRound} title="No permissions granted" description="This role currently grants nothing." />
                        ) : (
                            Object.entries(groups).map(([key, group]) => {
                                const names = Object.keys(group.permissions).filter((name) => role.is_super_admin || granted.has(name));

                                if (names.length === 0) {
                                    return null;
                                }

                                return (
                                    <section key={key} className="space-y-2">
                                        <h2 className="text-sm font-semibold">{group.label}</h2>
                                        <ul className="grid gap-1.5 sm:grid-cols-2">
                                            {names.map((name) => (
                                                <li
                                                    key={name}
                                                    className="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2"
                                                >
                                                    <span className="min-w-0 truncate text-sm">{group.permissions[name]}</span>
                                                    <Badge variant="outline" className="font-mono text-[11px]">
                                                        {name}
                                                    </Badge>
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                );
                            })
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
