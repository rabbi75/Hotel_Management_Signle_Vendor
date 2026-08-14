import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { DateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { DepartmentNode } from '@/types/companies';
import { Link, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Eye, Network, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface DepartmentsIndexProps {
    table: TablePayload<DepartmentNode>;
    tree: DepartmentNode[];
    can: { create: boolean };
}

export default function DepartmentsIndex({ table, tree, can }: DepartmentsIndexProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Departments' }];

    const mayManage = allows('companies.departments.manage');
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(node: DepartmentNode): Promise<void> {
        const ok = await confirm({
            title: `Delete the ${node.name} department?`,
            description:
                node.children.length > 0 || (node.children_count ?? 0) > 0
                    ? 'Its child departments are re-parented, and members lose their department assignment.'
                    : 'Members lose their department assignment.',
            variant: 'destructive',
            confirmLabel: 'Delete department',
        });

        if (ok) {
            router.delete(route('departments.destroy', node.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<DepartmentNode> = {
        name: (row) => (
            <Link
                href={route('departments.show', row.id)}
                className="block truncate rounded-sm text-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
            >
                {row.name}
            </Link>
        ),
        parent: (row) => <TextCell value={row.parent} muted />,
        manager: (row) => <TextCell value={row.manager} />,
        teams_count: (row) => <span className="tabular-nums">{row.teams_count ?? 0}</span>,
        created_at: (row) => <DateCell value={row.created_at} />,
    };

    function rowActions(row: DepartmentNode): RowAction[] {
        const actions: RowAction[] = [
            {
                id: 'view',
                label: 'View',
                icon: <Eye className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('departments.show', row.id)),
            },
        ];

        if (mayManage) {
            actions.push(
                {
                    id: 'edit',
                    label: 'Edit',
                    icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                    onSelect: () => router.visit(route('departments.edit', row.id)),
                },
                {
                    id: 'delete',
                    label: 'Delete',
                    destructive: true,
                    separatorBefore: true,
                    icon: <Trash2 className="size-4" aria-hidden="true" />,
                    onSelect: () => void remove(row),
                },
            );
        }

        return actions;
    }

    const newDepartment =
        can.create && mayManage ? (
            <Button asChild>
                <Link href={route('departments.create')}>
                    <Plus className="size-4" aria-hidden="true" />
                    New department
                </Link>
            </Button>
        ) : null;

    return (
        <AppLayout title="Departments" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader title="Departments" description="The reporting structure of this workspace." actions={newDepartment} />

                <Tabs defaultValue="tree" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="tree">Tree</TabsTrigger>
                        <TabsTrigger value="list">List</TabsTrigger>
                    </TabsList>

                    <TabsContent value="tree">
                        <Card>
                            <CardContent className="py-4">
                                {tree.length === 0 ? (
                                    <EmptyState
                                        icon={Network}
                                        title="No departments yet"
                                        description="Create a top-level department, then nest teams and people beneath it."
                                        action={
                                            can.create && mayManage ? (
                                                <Button asChild size="sm">
                                                    <Link href={route('departments.create')}>
                                                        <Plus className="size-4" aria-hidden="true" />
                                                        New department
                                                    </Link>
                                                </Button>
                                            ) : null
                                        }
                                    />
                                ) : (
                                    <ul role="tree" aria-label="Department hierarchy" className="space-y-1">
                                        {tree.map((node) => (
                                            <DepartmentBranch
                                                key={node.id}
                                                node={node}
                                                depth={0}
                                                canManage={mayManage}
                                                onDelete={(target) => void remove(target)}
                                            />
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="list">
                        <DataTable<DepartmentNode>
                            payload={table}
                            propKey="table"
                            name="departments"
                            columns={columns}
                            getRowId={(row) => String(row.id)}
                            rowActions={rowActions}
                            searchPlaceholder="Search departments…"
                            caption="Departments in this workspace"
                            emptyState={
                                filtered ? (
                                    <EmptyState
                                        icon={SearchX}
                                        title="No departments match these filters"
                                        description="Clear the parent filter to see the whole structure."
                                        className="border-0"
                                    />
                                ) : (
                                    <EmptyState
                                        icon={Network}
                                        title="No departments yet"
                                        description="Create a top-level department to get started."
                                        className="border-0"
                                    />
                                )
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

interface BranchProps {
    node: DepartmentNode;
    depth: number;
    canManage: boolean;
    onDelete: (node: DepartmentNode) => void;
}

function DepartmentBranch({ node, depth, canManage, onDelete }: BranchProps) {
    const [expanded, setExpanded] = useState(true);
    const hasChildren = node.children.length > 0;

    return (
        <li role="treeitem" aria-expanded={hasChildren ? expanded : undefined} className="min-w-0">
            <div
                className="flex flex-wrap items-center gap-2 rounded-md px-2 py-2 hover:bg-muted/50"
                style={{ paddingInlineStart: `${depth * 1.25}rem` }}
            >
                {hasChildren ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => setExpanded((current) => !current)}
                        aria-label={expanded ? `Collapse ${node.name}` : `Expand ${node.name}`}
                    >
                        {expanded ? (
                            <ChevronDown className="size-4" aria-hidden="true" />
                        ) : (
                            <ChevronRight className="size-4" aria-hidden="true" />
                        )}
                    </Button>
                ) : (
                    <span className="size-8" aria-hidden="true" />
                )}

                <span className="min-w-0 flex-1">
                    <Link
                        href={route('departments.show', node.id)}
                        className="block truncate rounded-sm text-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {node.name}
                    </Link>
                    {node.manager && <span className="block truncate text-xs text-muted-foreground">Managed by {node.manager}</span>}
                </span>

                {node.teams_count !== null && node.teams_count > 0 && <Badge variant="outline">{node.teams_count} teams</Badge>}

                {canManage && (
                    <span className="flex items-center gap-1">
                        <Button asChild variant="ghost" size="icon-sm">
                            <Link
                                href={`${route('departments.create')}?parent=${node.id}`}
                                aria-label={`Add a department under ${node.name}`}
                            >
                                <Plus className="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                        <Button asChild variant="ghost" size="icon-sm">
                            <Link href={route('departments.edit', node.id)} aria-label={`Edit ${node.name}`}>
                                <Pencil className="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => onDelete(node)}
                            aria-label={`Delete ${node.name}`}
                        >
                            <Trash2 className="size-4 text-destructive" aria-hidden="true" />
                        </Button>
                    </span>
                )}
            </div>

            {hasChildren && expanded && (
                <ul role="group" className="space-y-1">
                    {node.children.map((child) => (
                        <DepartmentBranch key={child.id} node={child} depth={depth + 1} canManage={canManage} onDelete={onDelete} />
                    ))}
                </ul>
            )}
        </li>
    );
}
