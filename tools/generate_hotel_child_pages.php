<?php

declare(strict_types=1);

$pages = dirname(__DIR__).'/resources/js/pages';

function write(string $path, string $contents): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $contents);
    echo str_replace('\\', '/', str_replace(dirname(__DIR__).DIRECTORY_SEPARATOR, '', $path))."\n";
}

$fieldHelper = <<<'TSX'
import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

const NONE = '__none__';

function Field({
    name,
    label,
    error,
    hint,
    required = false,
    className,
    children,
}: {
    name: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    className?: string;
    children: (props: {
        id: string;
        'aria-invalid': true | undefined;
        'aria-describedby': string | undefined;
        required: boolean | undefined;
    }) => ReactNode;
}) {
    const id = `field-${name}`;
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const describedBy = [error ? errorId : null, hint ? hintId : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                )}
            </Label>
            {children({ id, 'aria-invalid': error ? true : undefined, 'aria-describedby': describedBy, required: required || undefined })}
            {hint && !error && (
                <p id={hintId} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            {error && (
                <p id={errorId} className="flex items-start gap-1.5 text-sm text-destructive">
                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    {error}
                </p>
            )}
        </div>
    );
}

function emptyToNull(value: string): string | null {
    return value === '' ? null : value;
}

TSX;

// ---------- BUILDINGS ----------
write("{$pages}/buildings/building-form.tsx", <<<TSX
{$fieldHelper}
import type { BuildingRow, OptionMap } from '@/types/hotel';
import { useForm } from '@inertiajs/react';

interface Props {
    building?: BuildingRow;
    hotels: OptionMap;
    defaultHotelId?: number | null;
}

export function BuildingForm({ building, hotels, defaultHotelId = null }: Props) {
    const editing = building !== undefined;
    const form = useForm({
        hotel_id: String(building?.hotel_id ?? defaultHotelId ?? ''),
        name: building?.name ?? '',
        code: building?.code ?? '',
        description: building?.description ?? '',
        is_active: building?.is_active ?? true,
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : Number(values.hotel_id),
            code: emptyToNull(values.code),
            description: emptyToNull(values.description),
        }));

        if (editing && building) {
            form.put(route('buildings.update', building.id), { preserveScroll: true });
            return;
        }

        form.post(route('buildings.store'));
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This building could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Building</CardTitle>
                    <CardDescription>A wing, tower, or block within a hotel property.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <Field name="hotel_id" label="Hotel" error={errors.hotel_id} required className="sm:col-span-2">
                        {(props) => (
                            <Select value={data.hotel_id || NONE} onValueChange={(v) => setData('hotel_id', v === NONE ? '' : v)}>
                                <SelectTrigger {...props}>
                                    <SelectValue placeholder="Select hotel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Select hotel</SelectItem>
                                    {Object.entries(hotels).map(([id, label]) => (
                                        <SelectItem key={id} value={id}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field name="name" label="Name" error={errors.name} required>
                        {(props) => <Input {...props} value={data.name} onChange={(e) => setData('name', e.target.value)} />}
                    </Field>
                    <Field name="code" label="Code" error={errors.code}>
                        {(props) => <Input {...props} value={data.code} onChange={(e) => setData('code', e.target.value)} />}
                    </Field>
                    <Field name="description" label="Description" error={errors.description} className="sm:col-span-2">
                        {(props) => (
                            <Textarea {...props} rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        )}
                    </Field>
                    <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 sm:col-span-2">
                        <Label htmlFor="is_active">Active</Label>
                        <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                    </div>
                </CardContent>
            </Card>

            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save building' : 'Create building'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
TSX);

write("{$pages}/buildings/index.tsx", <<<'TSX'
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { BuildingRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { Building, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<BuildingRow>;
    can: { create: boolean };
}

export default function BuildingsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Buildings' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: BuildingRow): Promise<void> {
        const ok = await confirm({
            title: `Delete ${row.name}?`,
            description: 'Floors linked to this building may be affected.',
            variant: 'destructive',
            confirmLabel: 'Delete building',
        });
        if (ok) {
            router.delete(route('buildings.destroy', row.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<BuildingRow> = {
        name: (row) => <span className="font-medium">{row.name}</span>,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        code: (row) => <TextCell value={row.code} muted />,
        floors_count: (row) => <span className="tabular-nums">{row.floors_count ?? 0}</span>,
    };

    function rowActions(row: BuildingRow): RowAction[] {
        const actions: RowAction[] = [];
        if (allows('buildings.manage')) {
            actions.push({
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('buildings.edit', row.id)),
            });
            actions.push({
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            });
        }
        return actions;
    }

    return (
        <AppLayout title="Buildings" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Buildings"
                    description="Wings, towers, and blocks for each property."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('buildings.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New building
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="buildings"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search buildings…"
                    caption="Buildings in this workspace"
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : Building}
                            title={filtered ? 'No buildings match these filters' : 'No buildings yet'}
                            description={filtered ? 'Clear filters to see every building.' : 'Add a building to organize floors and rooms.'}
                            className="border-0"
                        />
                    }
                />
            </div>
        </AppLayout>
    );
}
TSX);

write("{$pages}/buildings/create.tsx", <<<'TSX'
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { BuildingForm } from './building-form';

interface Props {
    hotels: OptionMap;
    defaultHotelId: number | null;
}

export default function BuildingsCreate({ hotels, defaultHotelId }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Buildings', href: route('buildings.index') },
        { label: 'New building' },
    ];

    return (
        <AppLayout title="New building" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="New building"
                    description="Add a wing, tower, or block to a hotel."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('buildings.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <BuildingForm hotels={hotels} defaultHotelId={defaultHotelId} />
            </div>
        </AppLayout>
    );
}
TSX);

write("{$pages}/buildings/edit.tsx", <<<'TSX'
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { BuildingRow, OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { BuildingForm } from './building-form';

interface Props {
    building: BuildingRow;
    hotels: OptionMap;
}

export default function BuildingsEdit({ building, hotels }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Buildings', href: route('buildings.index') },
        { label: building.name },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${building.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={`Edit ${building.name}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('buildings.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <BuildingForm building={building} hotels={hotels} />
            </div>
        </AppLayout>
    );
}
TSX);

// ---------- FLOORS ----------
write("{$pages}/floors/floor-form.tsx", <<<TSX
{$fieldHelper}
import type { FloorRow, OptionMap } from '@/types/hotel';
import { useForm } from '@inertiajs/react';

interface Props {
    floor?: FloorRow;
    hotels: OptionMap;
    buildings: OptionMap;
    defaultHotelId?: number | null;
}

export function FloorForm({ floor, hotels, buildings, defaultHotelId = null }: Props) {
    const editing = floor !== undefined;
    const form = useForm({
        hotel_id: String(floor?.hotel_id ?? defaultHotelId ?? ''),
        building_id: floor?.building_id != null ? String(floor.building_id) : '',
        name: floor?.name ?? '',
        floor_number: String(floor?.floor_number ?? 0),
        code: floor?.code ?? '',
        description: floor?.description ?? '',
        is_active: floor?.is_active ?? true,
    });

    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            hotel_id: values.hotel_id === '' ? null : Number(values.hotel_id),
            building_id: values.building_id === '' ? null : Number(values.building_id),
            floor_number: values.floor_number === '' ? 0 : Number(values.floor_number),
            code: emptyToNull(values.code),
            description: emptyToNull(values.description),
        }));

        if (editing && floor) {
            form.put(route('floors.update', floor.id), { preserveScroll: true });
            return;
        }

        form.post(route('floors.store'));
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>This floor could not be saved</AlertTitle>
                    <AlertDescription>Review the highlighted fields and try again.</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Floor</CardTitle>
                    <CardDescription>Level within a hotel or building.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <Field name="hotel_id" label="Hotel" error={errors.hotel_id} required>
                        {(props) => (
                            <Select value={data.hotel_id || NONE} onValueChange={(v) => setData('hotel_id', v === NONE ? '' : v)}>
                                <SelectTrigger {...props}>
                                    <SelectValue placeholder="Select hotel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Select hotel</SelectItem>
                                    {Object.entries(hotels).map(([id, label]) => (
                                        <SelectItem key={id} value={id}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field name="building_id" label="Building" error={errors.building_id} hint="Optional">
                        {(props) => (
                            <Select value={data.building_id || NONE} onValueChange={(v) => setData('building_id', v === NONE ? '' : v)}>
                                <SelectTrigger {...props}>
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>None</SelectItem>
                                    {Object.entries(buildings).map(([id, label]) => (
                                        <SelectItem key={id} value={id}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field name="name" label="Name" error={errors.name} required>
                        {(props) => <Input {...props} value={data.name} onChange={(e) => setData('name', e.target.value)} />}
                    </Field>
                    <Field name="floor_number" label="Floor number" error={errors.floor_number} required>
                        {(props) => (
                            <Input
                                {...props}
                                type="number"
                                value={data.floor_number}
                                onChange={(e) => setData('floor_number', e.target.value)}
                            />
                        )}
                    </Field>
                    <Field name="code" label="Code" error={errors.code}>
                        {(props) => <Input {...props} value={data.code} onChange={(e) => setData('code', e.target.value)} />}
                    </Field>
                    <Field name="description" label="Description" error={errors.description} className="sm:col-span-2">
                        {(props) => (
                            <Textarea {...props} rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        )}
                    </Field>
                    <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 sm:col-span-2">
                        <Label htmlFor="is_active">Active</Label>
                        <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                    </div>
                </CardContent>
            </Card>

            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save floor' : 'Create floor'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
TSX);

write("{$pages}/floors/index.tsx", <<<'TSX'
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, TablePayload } from '@/types';
import type { FloorRow } from '@/types/hotel';
import { Link, router } from '@inertiajs/react';
import { Layers, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';

interface Props {
    table: TablePayload<FloorRow>;
    can: { create: boolean };
}

export default function FloorsIndex({ table, can }: Props) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Floors' }];
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    async function remove(row: FloorRow): Promise<void> {
        const ok = await confirm({
            title: `Delete ${row.name}?`,
            description: 'Rooms on this floor may need reassignment.',
            variant: 'destructive',
            confirmLabel: 'Delete floor',
        });
        if (ok) {
            router.delete(route('floors.destroy', row.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<FloorRow> = {
        name: (row) => <span className="font-medium">{row.name}</span>,
        floor_number: (row) => <span className="tabular-nums">{row.floor_number}</span>,
        hotel: (row) => <TextCell value={row.hotel} muted />,
        building: (row) => <TextCell value={row.building} muted />,
        rooms_count: (row) => <span className="tabular-nums">{row.rooms_count ?? 0}</span>,
    };

    function rowActions(row: FloorRow): RowAction[] {
        const actions: RowAction[] = [];
        if (allows('floors.manage')) {
            actions.push({
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => router.visit(route('floors.edit', row.id)),
            });
            actions.push({
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            });
        }
        return actions;
    }

    return (
        <AppLayout title="Floors" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Floors"
                    description="Levels used to place rooms in each property."
                    actions={
                        can.create ? (
                            <Button asChild>
                                <Link href={route('floors.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New floor
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    payload={table}
                    propKey="table"
                    name="floors"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search floors…"
                    caption="Floors in this workspace"
                    emptyState={
                        <EmptyState
                            icon={filtered ? SearchX : Layers}
                            title={filtered ? 'No floors match these filters' : 'No floors yet'}
                            description={filtered ? 'Clear filters to see every floor.' : 'Create floors to organize rooms by level.'}
                            className="border-0"
                        />
                    }
                />
            </div>
        </AppLayout>
    );
}
TSX);

write("{$pages}/floors/create.tsx", <<<'TSX'
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FloorForm } from './floor-form';

interface Props {
    hotels: OptionMap;
    buildings: OptionMap;
    defaultHotelId: number | null;
}

export default function FloorsCreate({ hotels, buildings, defaultHotelId }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Floors', href: route('floors.index') },
        { label: 'New floor' },
    ];

    return (
        <AppLayout title="New floor" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="New floor"
                    description="Add a level to a hotel or building."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('floors.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <FloorForm hotels={hotels} buildings={buildings} defaultHotelId={defaultHotelId} />
            </div>
        </AppLayout>
    );
}
TSX);

write("{$pages}/floors/edit.tsx", <<<'TSX'
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { FloorRow, OptionMap } from '@/types/hotel';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FloorForm } from './floor-form';

interface Props {
    floor: FloorRow;
    hotels: OptionMap;
    buildings: OptionMap;
}

export default function FloorsEdit({ floor, hotels, buildings }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Floors', href: route('floors.index') },
        { label: floor.name },
        { label: 'Edit' },
    ];

    return (
        <AppLayout title={`Edit ${floor.name}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={`Edit ${floor.name}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('floors.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />
                <FloorForm floor={floor} hotels={hotels} buildings={buildings} />
            </div>
        </AppLayout>
    );
}
TSX);

echo "buildings + floors done\n";
