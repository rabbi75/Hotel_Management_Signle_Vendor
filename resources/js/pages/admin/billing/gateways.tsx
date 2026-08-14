import { PageHeader } from '@/components/app-shell/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { AdminLayout } from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { PaymentGatewayRow } from '@/types/billing';
import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { restrictToParentElement, restrictToVerticalAxis } from '@dnd-kit/modifiers';
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import { CircleAlert, CircleCheck, GripVertical, Plug, Settings2 } from 'lucide-react';
import { useState } from 'react';

interface GatewaysPageProps {
    gateways: PaymentGatewayRow[];
    currencies: string[];
    can: { manage: boolean };
}

/** Comma-separated list ⇄ array, for the currency and country inputs. */
function toList(value: string): string[] {
    return value
        .split(',')
        .map((entry) => entry.trim().toUpperCase())
        .filter((entry) => entry !== '');
}

function GatewayRow({
    gateway,
    onConfigure,
    onToggle,
    disabled,
}: {
    gateway: PaymentGatewayRow;
    onConfigure: (gateway: PaymentGatewayRow) => void;
    onToggle: (gateway: PaymentGatewayRow, enabled: boolean) => void;
    disabled: boolean;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: gateway.id });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn('rounded-lg border border-border bg-card', isDragging && 'z-10 opacity-80 shadow-lg')}
        >
            <div className="flex items-center gap-3 p-3">
                <button
                    type="button"
                    aria-label={`Reorder ${gateway.label}`}
                    className="cursor-grab rounded p-1 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" aria-hidden="true" />
                </button>

                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-card-foreground">
                        {gateway.label}
                        {gateway.is_enabled && gateway.is_test_mode && <Badge variant="warning">Test mode</Badge>}
                        {gateway.is_enabled && !gateway.is_ready && (
                            <Badge variant="destructive" className="gap-1">
                                <CircleAlert className="size-3" aria-hidden="true" />
                                Incomplete
                            </Badge>
                        )}
                        {!gateway.supports_refunds && <Badge variant="outline">No refunds</Badge>}
                    </p>
                    <p className="mt-0.5 truncate text-xs text-muted-foreground">{gateway.description}</p>

                    {gateway.last_test_error && (
                        <p className="mt-1 truncate text-xs text-destructive">Last test failed: {gateway.last_test_error}</p>
                    )}
                </div>

                <div className="hidden shrink-0 text-right text-xs text-muted-foreground sm:block">
                    <p>{gateway.recent.transactions} payments · 30d</p>
                    {gateway.recent.unprocessed > 0 && (
                        <p className="text-destructive">{gateway.recent.unprocessed} unprocessed events</p>
                    )}
                </div>

                <Switch
                    checked={gateway.is_enabled}
                    disabled={disabled}
                    aria-label={`${gateway.is_enabled ? 'Disable' : 'Enable'} ${gateway.label}`}
                    onCheckedChange={(checked) => onToggle(gateway, checked)}
                />

                <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => onConfigure(gateway)}>
                    <Settings2 className="size-4" aria-hidden="true" />
                    Configure
                </Button>
            </div>
        </li>
    );
}

export default function AdminGatewaysPage({ gateways, currencies, can }: GatewaysPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Platform' }, { label: 'Payment gateways' }];

    const [order, setOrder] = useState(gateways);
    const [editing, setEditing] = useState<PaymentGatewayRow | null>(null);
    const [form, setForm] = useState<Record<string, string>>({});
    const [currencyText, setCurrencyText] = useState('');
    const [countryText, setCountryText] = useState('');
    const [saving, setSaving] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    function configure(gateway: PaymentGatewayRow): void {
        setEditing(gateway);
        setCurrencyText(gateway.currencies.join(', '));
        setCountryText(gateway.countries.join(', '));
        setForm(
            Object.fromEntries(Object.entries(gateway.credentials).map(([key, field]) => [key, field.secret ? '' : field.value])),
        );
    }

    function toggle(gateway: PaymentGatewayRow, enabled: boolean): void {
        setOrder((current) => current.map((row) => (row.id === gateway.id ? { ...row, is_enabled: enabled } : row)));

        router.put(
            route('admin.gateways.update', gateway.id),
            {
                is_enabled: enabled,
                is_test_mode: gateway.is_test_mode,
                currencies: gateway.currencies,
                countries: gateway.countries,
                credentials: {},
            },
            { preserveScroll: true },
        );
    }

    function save(): void {
        if (!editing) {
            return;
        }

        setSaving(true);

        router.put(
            route('admin.gateways.update', editing.id),
            {
                is_enabled: editing.is_enabled,
                is_test_mode: editing.is_test_mode,
                currencies: toList(currencyText),
                countries: toList(countryText),
                // Only fields the operator actually typed into are sent; an
                // untouched secret stays untouched on the server.
                credentials: Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setEditing(null);
                },
            },
        );
    }

    function onDragEnd(event: DragEndEvent): void {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const from = order.findIndex((row) => row.id === active.id);
        const to = order.findIndex((row) => row.id === over.id);

        if (from === -1 || to === -1) {
            return;
        }

        const next = [...order];
        const [moved] = next.splice(from, 1);

        if (!moved) {
            return;
        }

        next.splice(to, 0, moved);
        setOrder(next);

        router.post(route('admin.gateways.reorder'), { ids: next.map((row) => row.id) }, { preserveScroll: true });
    }

    const live = order.filter((row) => row.is_enabled && !row.is_test_mode).length;

    return (
        <AdminLayout title="Payment gateways" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Payment gateways"
                    description="Which processors this installation accepts, and in what order they are offered at checkout."
                />

                {live === 0 && (
                    <Alert>
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>No processor is taking live payments</AlertTitle>
                        <AlertDescription>
                            Every enabled gateway is either offline or in test mode. Customers can subscribe, but no money will be
                            captured.
                        </AlertDescription>
                    </Alert>
                )}

                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    modifiers={[restrictToVerticalAxis, restrictToParentElement]}
                    onDragEnd={onDragEnd}
                >
                    <SortableContext items={order.map((row) => row.id)} strategy={verticalListSortingStrategy}>
                        <ul className="space-y-2">
                            {order.map((gateway) => (
                                <GatewayRow
                                    key={gateway.id}
                                    gateway={gateway}
                                    disabled={!can.manage}
                                    onConfigure={configure}
                                    onToggle={toggle}
                                />
                            ))}
                        </ul>
                    </SortableContext>
                </DndContext>
            </div>

            <Sheet open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{editing?.label}</SheetTitle>
                        <SheetDescription>{editing?.description}</SheetDescription>
                    </SheetHeader>

                    {editing && (
                        <div className="space-y-5 px-4 pb-6">
                            <div className="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
                                <div>
                                    <Label htmlFor="gateway-test">Test mode</Label>
                                    <p className="text-xs text-muted-foreground">
                                        Use the sandbox environment rather than live keys.
                                    </p>
                                </div>
                                <Switch
                                    id="gateway-test"
                                    checked={editing.is_test_mode}
                                    onCheckedChange={(checked) => setEditing({ ...editing, is_test_mode: checked })}
                                />
                            </div>

                            {Object.entries(editing.credentials).length === 0 ? (
                                <p className="text-sm text-muted-foreground">This gateway needs no credentials.</p>
                            ) : (
                                Object.entries(editing.credentials).map(([key, field]) => (
                                    <div key={key} className="space-y-2">
                                        <Label htmlFor={`cred-${key}`}>
                                            {field.label}
                                            {field.is_set && (
                                                <span className="ml-2 inline-flex items-center gap-1 text-xs font-normal text-success">
                                                    <CircleCheck className="size-3" aria-hidden="true" />
                                                    set
                                                </span>
                                            )}
                                        </Label>
                                        <Input
                                            id={`cred-${key}`}
                                            type={field.secret ? 'password' : 'text'}
                                            value={form[key] ?? ''}
                                            placeholder={field.secret && field.is_set ? field.value : undefined}
                                            autoComplete="off"
                                            onChange={(event) => setForm({ ...form, [key]: event.target.value })}
                                        />
                                        {field.help && <p className="text-xs text-muted-foreground">{field.help}</p>}
                                        {field.secret && field.is_set && (
                                            <p className="text-xs text-muted-foreground">Leave blank to keep the stored value.</p>
                                        )}
                                    </div>
                                ))
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="gateway-currencies">Currencies</Label>
                                <Input
                                    id="gateway-currencies"
                                    value={currencyText}
                                    placeholder="USD, EUR, GBP"
                                    onChange={(event) => setCurrencyText(event.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Leave empty for no restriction. Known: {currencies.join(', ')}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="gateway-countries">Countries</Label>
                                <Input
                                    id="gateway-countries"
                                    value={countryText}
                                    placeholder="GB, DE, NG"
                                    onChange={(event) => setCountryText(event.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">Two-letter codes. Leave empty for no restriction.</p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button type="button" disabled={saving} onClick={save}>
                                    {saving ? 'Saving…' : 'Save'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(route('admin.gateways.test', editing.id), {}, { preserveScroll: true })
                                    }
                                >
                                    <Plug className="size-4" aria-hidden="true" />
                                    Test connection
                                </Button>
                            </div>

                            <div className="rounded-lg border border-border p-3 text-xs text-muted-foreground">
                                <p className="font-medium text-foreground">Last 30 days</p>
                                <p className="mt-1">
                                    {editing.recent.transactions} payments · {editing.recent.failures} failed ·{' '}
                                    {editing.recent.events} webhook events
                                </p>
                                {editing.last_tested_at && (
                                    <p className="mt-1">Last tested {new Date(editing.last_tested_at).toLocaleString()}</p>
                                )}
                            </div>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AdminLayout>
    );
}
