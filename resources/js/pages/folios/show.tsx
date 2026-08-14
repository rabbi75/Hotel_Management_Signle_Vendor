import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { CurrencyCell } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { GuestFolioRow, OptionMap } from '@/types/folio';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Trash2 } from 'lucide-react';

const NONE = '__none__';

interface Props {
    folio: GuestFolioRow;
    services: OptionMap;
    can: { manage: boolean; add_charge: boolean; record_payment: boolean; close: boolean };
}

export default function FoliosShow({ folio, services, can }: Props) {
    const confirm = useConfirm();
    const chargeForm = useForm({
        hotel_service_id: '',
        description: '',
        quantity: '1',
        unit_price: '0',
    });
    const paymentForm = useForm({
        amount: String(folio.balance || 0),
        method: 'cash',
        reference: '',
        notes: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Guest folios', href: route('folios.index') },
        { label: folio.number },
    ];

    function submitCharge(event: React.FormEvent): void {
        event.preventDefault();
        chargeForm.transform((v) => ({
            ...v,
            hotel_service_id: v.hotel_service_id === '' ? null : v.hotel_service_id,
            quantity: Number(v.quantity),
            unit_price: Number(v.unit_price),
        }));
        chargeForm.post(route('folios.items.store', folio.id), { preserveScroll: true, onSuccess: () => chargeForm.reset('description') });
    }

    function submitPayment(event: React.FormEvent): void {
        event.preventDefault();
        paymentForm.transform((v) => ({
            ...v,
            amount: Number(v.amount),
        }));
        paymentForm.post(route('folios.payments.store', folio.id), { preserveScroll: true });
    }

    async function closeFolio(): Promise<void> {
        const ok = await confirm({
            title: 'Close this folio?',
            description: folio.balance > 0 ? 'There is still an outstanding balance. Close anyway?' : undefined,
            confirmLabel: 'Close folio',
        });
        if (ok) {
            router.post(route('folios.close', folio.id), { allow_balance: folio.balance > 0 }, { preserveScroll: true });
        }
    }

    async function removeItem(itemId: number): Promise<void> {
        const ok = await confirm({ title: 'Remove this charge?', variant: 'destructive', confirmLabel: 'Remove' });
        if (ok) router.delete(route('folios.items.destroy', [folio.id, itemId]), { preserveScroll: true });
    }

    return (
        <AppLayout title={folio.number} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={folio.number}
                    description={`${folio.guest ?? 'Guest'} · ${folio.hotel ?? 'Hotel'}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={route('folios.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back
                                </Link>
                            </Button>
                            {folio.invoice_id && (
                                <Button asChild variant="outline">
                                    <a href={route('guest-invoices.show', folio.invoice_id)} target="_blank" rel="noreferrer">
                                        <ExternalLink className="size-4" aria-hidden="true" />
                                        Invoice {folio.invoice_number}
                                    </a>
                                </Button>
                            )}
                            {can.close && folio.is_open && (
                                <Button onClick={() => void closeFolio()}>Close folio</Button>
                            )}
                        </div>
                    }
                />

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Summary</CardTitle>
                        <Badge variant="outline">{folio.status_label}</Badge>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <p className="text-muted-foreground">Total</p>
                            <CurrencyCell value={folio.total} minorUnits />
                        </div>
                        <div>
                            <p className="text-muted-foreground">Paid</p>
                            <CurrencyCell value={folio.paid_amount} minorUnits />
                        </div>
                        <div>
                            <p className="text-muted-foreground">Balance</p>
                            <CurrencyCell value={folio.balance} minorUnits />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Charges</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="pb-2 pr-4">Description</th>
                                        <th className="pb-2 pr-4">Type</th>
                                        <th className="pb-2 pr-4 text-right">Qty</th>
                                        <th className="pb-2 pr-4 text-right">Amount</th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {folio.items.map((item) => (
                                        <tr key={item.id} className="border-b">
                                            <td className="py-2 pr-4">{item.description}</td>
                                            <td className="py-2 pr-4 text-muted-foreground">{item.type_label}</td>
                                            <td className="py-2 pr-4 text-right tabular-nums">{item.quantity}</td>
                                            <td className="py-2 pr-4 text-right">
                                                <CurrencyCell value={item.amount} minorUnits />
                                            </td>
                                            <td className="py-2 text-right">
                                                {can.manage && folio.is_open && item.type !== 'room' && item.type !== 'tax' && (
                                                    <Button type="button" variant="ghost" size="icon" onClick={() => void removeItem(item.id)}>
                                                        <Trash2 className="size-4" aria-hidden="true" />
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {can.add_charge && folio.is_open && (
                            <form onSubmit={submitCharge} className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Catalog service</Label>
                                    <Select
                                        value={chargeForm.data.hotel_service_id || NONE}
                                        onValueChange={(v) => chargeForm.setData('hotel_service_id', v === NONE ? '' : v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Optional — pick from catalog" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>Custom charge</SelectItem>
                                            {Object.entries(services).map(([id, name]) => (
                                                <SelectItem key={id} value={id}>
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Description</Label>
                                    <Input value={chargeForm.data.description} onChange={(e) => chargeForm.setData('description', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Quantity</Label>
                                    <Input type="number" value={chargeForm.data.quantity} onChange={(e) => chargeForm.setData('quantity', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Unit price (minor units)</Label>
                                    <Input type="number" value={chargeForm.data.unit_price} onChange={(e) => chargeForm.setData('unit_price', e.target.value)} />
                                </div>
                                <div className="sm:col-span-2">
                                    <Button type="submit" disabled={chargeForm.processing}>
                                        Post charge
                                    </Button>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payments</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="pb-2 pr-4">Date</th>
                                        <th className="pb-2 pr-4">Method</th>
                                        <th className="pb-2 pr-4 text-right">Amount</th>
                                        <th className="pb-2">Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {folio.payments.map((payment) => (
                                        <tr key={payment.id} className="border-b">
                                            <td className="py-2 pr-4">{payment.paid_at ? new Date(payment.paid_at).toLocaleDateString() : '—'}</td>
                                            <td className="py-2 pr-4">{payment.method_label}</td>
                                            <td className="py-2 pr-4 text-right">
                                                <CurrencyCell value={payment.amount} minorUnits />
                                            </td>
                                            <td className="py-2 text-muted-foreground">{payment.reference || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {can.record_payment && folio.is_open && (
                            <form onSubmit={submitPayment} className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Amount (minor units)</Label>
                                    <Input type="number" value={paymentForm.data.amount} onChange={(e) => paymentForm.setData('amount', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Method</Label>
                                    <Select value={paymentForm.data.method} onValueChange={(v) => paymentForm.setData('method', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="cash">Cash</SelectItem>
                                            <SelectItem value="card">Card</SelectItem>
                                            <SelectItem value="bank_transfer">Bank transfer</SelectItem>
                                            <SelectItem value="mobile_wallet">Mobile wallet</SelectItem>
                                            <SelectItem value="other">Other</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Reference</Label>
                                    <Input value={paymentForm.data.reference} onChange={(e) => paymentForm.setData('reference', e.target.value)} />
                                </div>
                                <div className="flex items-end">
                                    <Button type="submit" disabled={paymentForm.processing}>
                                        Record payment
                                    </Button>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
