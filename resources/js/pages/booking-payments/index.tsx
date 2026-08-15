import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PublicPaymentMethod } from '@/types/booking';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    methods: PublicPaymentMethod[];
}

function MethodCard({ method }: { method: PublicPaymentMethod }) {
    const form = useForm({
        name: method.name,
        instructions: method.instructions ?? '',
        is_enabled: method.is_enabled,
        is_default: method.is_default,
        sort_order: method.sort_order,
    });

    return (
        <form
            className="space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(route('booking-payments.update', method.id), { preserveScroll: true });
            }}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium">{method.driver_label}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{method.description}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {method.driver === 'cash_on_delivery' && <Badge variant="secondary">COD</Badge>}
                    {method.requires_prepaid && <Badge variant="outline">Prepaid</Badge>}
                    {method.is_default && <Badge>Default</Badge>}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label htmlFor={`name-${method.id}`}>Display name</Label>
                    <Input id={`name-${method.id}`} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor={`sort-${method.id}`}>Sort order</Label>
                    <Input
                        id={`sort-${method.id}`}
                        type="number"
                        min={0}
                        value={form.data.sort_order}
                        onChange={(event) => form.setData('sort_order', Number(event.target.value))}
                    />
                </div>
            </div>

            <div className="space-y-1.5">
                <Label htmlFor={`instructions-${method.id}`}>Guest instructions</Label>
                <Textarea
                    id={`instructions-${method.id}`}
                    rows={3}
                    value={form.data.instructions}
                    onChange={(event) => form.setData('instructions', event.target.value)}
                />
            </div>

            <div className="flex flex-wrap items-center gap-6">
                <label className="flex items-center gap-2 text-sm">
                    <Switch checked={form.data.is_enabled} onCheckedChange={(checked) => form.setData('is_enabled', checked)} />
                    Enabled on checkout
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <Switch checked={form.data.is_default} onCheckedChange={(checked) => form.setData('is_default', checked)} />
                    Default method
                </label>
                <Button type="submit" size="sm" className="ml-auto" disabled={form.processing}>
                    Save
                </Button>
            </div>
        </form>
    );
}

export default function BookingPaymentsIndex({ methods }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Payment methods' }];
    const [query, setQuery] = useState('');
    const visible = methods.filter((method) => method.name.toLowerCase().includes(query.toLowerCase()) || method.driver.includes(query.toLowerCase()));

    return (
        <AppLayout title="Payment methods" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Booking payment methods"
                    description="Enable cash on delivery plus the popular card and wallet options guests see on the public checkout."
                />
                <Input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Filter methods…" className="max-w-sm" />
                <div className="grid gap-4">
                    {visible.map((method) => (
                        <MethodCard key={method.id} method={method} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
