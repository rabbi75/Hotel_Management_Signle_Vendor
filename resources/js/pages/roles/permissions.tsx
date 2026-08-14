import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { MatrixGroup, MatrixRole } from '@/types/roles';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CircleAlert, Lock, ShieldCheck } from 'lucide-react';
import { useMemo } from 'react';

interface PermissionMatrixProps {
    groups: MatrixGroup[];
    roles: MatrixRole[];
}

type Matrix = Record<string, string[]>;

interface MatrixFormValues {
    matrix: Matrix;
    [key: string]: Matrix;
}

export default function PermissionMatrix({ groups, roles }: PermissionMatrixProps) {
    const { can } = usePermissions();
    const editable = can('roles.update');

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Roles', href: route('roles.index') },
        { label: 'Permission matrix' },
    ];

    const allPermissions = useMemo(() => groups.flatMap((group) => group.permissions), [groups]);

    const form = useForm<MatrixFormValues>({
        matrix: Object.fromEntries(roles.map((role) => [String(role.id), [...role.permissions]])),
    });

    const { data, setData, errors, processing, isDirty } = form;

    function permissionsFor(roleId: number): string[] {
        return data.matrix[String(roleId)] ?? [];
    }

    function write(roleId: number, next: string[]): void {
        setData('matrix', { ...data.matrix, [String(roleId)]: [...new Set(next)].sort() });
    }

    function toggleCell(roleId: number, permission: string, checked: boolean): void {
        const current = permissionsFor(roleId);

        write(roleId, checked ? [...current, permission] : current.filter((entry) => entry !== permission));
    }

    /** Grants or revokes every permission for one role. */
    function toggleRow(roleId: number, checked: boolean): void {
        write(roleId, checked ? allPermissions.map((permission) => permission.name) : []);
    }

    /** Grants or revokes one permission across every editable role. */
    function toggleColumn(permission: string, checked: boolean): void {
        const next: Matrix = {};

        for (const role of roles) {
            const current = permissionsFor(role.id);

            next[String(role.id)] = [
                ...new Set(checked ? [...current, permission] : current.filter((entry) => entry !== permission)),
            ].sort();
        }

        setData('matrix', next);
    }

    function toggleGroupForRole(roleId: number, group: MatrixGroup, checked: boolean): void {
        const names = group.permissions.map((permission) => permission.name);
        const current = permissionsFor(roleId);

        write(roleId, checked ? [...current, ...names] : current.filter((entry) => !names.includes(entry)));
    }

    function columnState(permission: string): boolean | 'indeterminate' {
        const holders = roles.filter((role) => permissionsFor(role.id).includes(permission));

        return holders.length === 0 ? false : holders.length === roles.length ? true : 'indeterminate';
    }

    function rowState(roleId: number): boolean | 'indeterminate' {
        const count = permissionsFor(roleId).length;

        return count === 0 ? false : count === allPermissions.length ? true : 'indeterminate';
    }

    const changedRoles = roles.filter((role) => {
        const next = [...permissionsFor(role.id)].sort();
        const before = [...role.permissions].sort();

        return next.length !== before.length || next.some((entry, index) => entry !== before[index]);
    });

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put(route('roles.permissions.update'), { preserveScroll: true });
    }

    const matrixError = errors.matrix;

    if (roles.length === 0) {
        return (
            <AppLayout title="Permission matrix" breadcrumbs={breadcrumbs}>
                <div className="space-y-6">
                    <PageHeader title="Permission matrix" description="Every editable role crossed with the permission registry." />
                    <EmptyState
                        icon={ShieldCheck}
                        title="No editable roles"
                        description="Create a role first — the super-admin role is deliberately excluded from this grid."
                        action={
                            can('roles.create') ? (
                                <Button asChild size="sm">
                                    <Link href={route('roles.create')}>New role</Link>
                                </Button>
                            ) : null
                        }
                    />
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Permission matrix" breadcrumbs={breadcrumbs}>
            <form onSubmit={submit} className="space-y-6">
                <PageHeader
                    title="Permission matrix"
                    description="Every editable role crossed with the permission registry. One save applies the whole grid."
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={route('roles.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back to roles
                                </Link>
                            </Button>
                            {editable && (
                                <Button type="submit" loading={processing} disabled={!isDirty}>
                                    Save matrix
                                </Button>
                            )}
                        </>
                    }
                />

                <div aria-live="polite" className="min-h-6">
                    {isDirty ? (
                        <Badge variant="warning">
                            Unsaved changes to {changedRoles.length} role{changedRoles.length === 1 ? '' : 's'}
                        </Badge>
                    ) : (
                        <span className="text-sm text-muted-foreground">No pending changes.</span>
                    )}
                </div>

                {matrixError && (
                    <Alert variant="destructive">
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>The matrix could not be saved</AlertTitle>
                        <AlertDescription>{matrixError}</AlertDescription>
                    </Alert>
                )}

                <div className="overflow-auto rounded-lg border border-border" style={{ maxHeight: '70svh' }}>
                    <table className="w-full border-collapse text-sm">
                        <caption className="sr-only">
                            Roles down the side, permissions across the top. Each cell grants one permission to one role.
                        </caption>

                        <thead>
                            <tr>
                                <th
                                    scope="col"
                                    className="sticky top-0 left-0 z-30 min-w-52 border-r border-b border-border bg-card p-3 text-left align-bottom"
                                >
                                    Role
                                </th>
                                {groups.map((group) => (
                                    <th
                                        key={group.key}
                                        scope="colgroup"
                                        colSpan={group.permissions.length}
                                        className="sticky top-0 z-20 border-r border-b border-border bg-muted/50 px-3 py-2 text-left text-xs font-semibold whitespace-nowrap"
                                    >
                                        {group.label}
                                    </th>
                                ))}
                            </tr>
                            <tr>
                                <th
                                    scope="col"
                                    className="sticky left-0 z-30 border-r border-b border-border bg-card p-3 text-left text-xs font-normal text-muted-foreground"
                                    style={{ top: '2.25rem' }}
                                >
                                    Toggle a whole row or column
                                </th>
                                {allPermissions.map((permission) => (
                                    <th
                                        key={permission.name}
                                        scope="col"
                                        className="z-10 min-w-36 border-b border-border bg-card p-2 text-left align-bottom"
                                        style={{ position: 'sticky', top: '2.25rem' }}
                                    >
                                        <span className="block text-xs leading-tight font-medium">{permission.label}</span>
                                        <span className="mt-0.5 block font-mono text-[10px] break-all text-muted-foreground">
                                            {permission.name}
                                        </span>
                                        {editable && (
                                            <Checkbox
                                                className="mt-1.5"
                                                checked={columnState(permission.name)}
                                                onCheckedChange={(checked) => toggleColumn(permission.name, checked === true)}
                                                aria-label={`Grant “${permission.label}” to every role`}
                                            />
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody>
                            {/*
                                The super-admin role is granted by a gate rather than by rows in
                                this grid, so it is shown for completeness but can never be edited.
                            */}
                            <tr className="bg-muted/30">
                                <th
                                    scope="row"
                                    className="sticky left-0 z-10 border-r border-b border-border bg-muted/60 p-3 text-left align-middle"
                                >
                                    <span className="flex items-center gap-2">
                                        <Lock className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        <span className="font-medium">Super admin</span>
                                    </span>
                                    <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                        Bypasses every check via a gate; not editable here.
                                    </span>
                                </th>
                                {allPermissions.map((permission) => (
                                    <td key={permission.name} className="border-b border-border p-2 text-center">
                                        <Checkbox checked disabled aria-label={`Super admin always holds ${permission.label}`} />
                                    </td>
                                ))}
                            </tr>

                            {roles.map((role) => (
                                <tr key={role.id} className="hover:bg-muted/30">
                                    <th
                                        scope="row"
                                        className="sticky left-0 z-10 border-r border-b border-border bg-card p-3 text-left align-middle"
                                    >
                                        <span className="flex items-center gap-2">
                                            {editable && (
                                                <Checkbox
                                                    checked={rowState(role.id)}
                                                    onCheckedChange={(checked) => toggleRow(role.id, checked === true)}
                                                    aria-label={`Grant every permission to ${role.label}`}
                                                />
                                            )}
                                            <span className="font-medium">{role.label}</span>
                                        </span>
                                        <span className="mt-1 block font-mono text-[11px] font-normal text-muted-foreground">
                                            {role.name}
                                        </span>
                                        {editable && (
                                            <span className="mt-2 flex flex-wrap gap-1">
                                                {groups.map((group) => {
                                                    const names = group.permissions.map((permission) => permission.name);
                                                    const held = names.filter((name) => permissionsFor(role.id).includes(name)).length;

                                                    return (
                                                        <Button
                                                            key={group.key}
                                                            type="button"
                                                            variant="ghost"
                                                            size="xs"
                                                            onClick={() => toggleGroupForRole(role.id, group, held !== names.length)}
                                                        >
                                                            {group.label} {held}/{names.length}
                                                        </Button>
                                                    );
                                                })}
                                            </span>
                                        )}
                                    </th>

                                    {allPermissions.map((permission) => {
                                        const checked = permissionsFor(role.id).includes(permission.name);

                                        return (
                                            <td
                                                key={permission.name}
                                                className={cn('border-b border-border p-2 text-center', checked && 'bg-primary/5')}
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    disabled={!editable}
                                                    onCheckedChange={(next) => toggleCell(role.id, permission.name, next === true)}
                                                    aria-label={`${role.label}: ${permission.label}`}
                                                />
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {editable && (
                    <div className="flex justify-end">
                        <Button type="submit" loading={processing} disabled={!isDirty}>
                            Save matrix
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
