import { PageHeader } from '@/components/app-shell/page-header';
import { StatCard } from '@/components/charts/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { AdminLayout } from '@/layouts/admin-layout';
import type { BreadcrumbItem } from '@/types';
import type { FailedPaymentRow, MoneyValue, OverdueInvoiceRow, PastDueRow } from '@/types/billing';
import { router } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, CircleAlert, CircleDollarSign, Clock, PartyPopper } from 'lucide-react';
import { useState } from 'react';

interface DunningPageProps {
    pastDue: PastDueRow[];
    overdue: OverdueInvoiceRow[];
    failed: FailedPaymentRow[];
    graceDays: number;
    exposure: MoneyValue;
    can: { manage: boolean };
}

function relative(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

/**
 * How long until the scheduler revokes access. Rendered as its own component
 * because the boundary cases carry the meaning: zero or below means the next
 * run cuts the workspace off.
 */
function GraceCountdown({ days }: { days: number | null }) {
    if (days === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    if (days <= 0) {
        return (
            <Badge variant="destructive" className="gap-1">
                <CircleAlert className="size-3" aria-hidden="true" />
                Access revoked next run
            </Badge>
        );
    }

    return (
        <Badge variant={days <= 1 ? 'destructive' : 'warning'} className="gap-1">
            <Clock className="size-3" aria-hidden="true" />
            {days} day{days === 1 ? '' : 's'} left
        </Badge>
    );
}

export default function AdminDunningPage({ pastDue, overdue, failed, graceDays, exposure, can }: DunningPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Platform' }, { label: 'Collections' }];

    const [settling, setSettling] = useState<OverdueInvoiceRow | null>(null);
    const [reference, setReference] = useState('');
    const [extending, setExtending] = useState<PastDueRow | null>(null);
    const [days, setDays] = useState('7');
    const [working, setWorking] = useState(false);

    function settle(): void {
        if (!settling) {
            return;
        }

        setWorking(true);
        router.post(
            route('admin.dunning.settle', settling.id),
            { reference: reference || null },
            {
                preserveScroll: true,
                onFinish: () => {
                    setWorking(false);
                    setSettling(null);
                    setReference('');
                },
            },
        );
    }

    function extendGrace(): void {
        if (!extending) {
            return;
        }

        setWorking(true);
        router.post(
            route('admin.dunning.grace', extending.id),
            { days: Number(days) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setWorking(false);
                    setExtending(null);
                },
            },
        );
    }

    const nothingToDo = pastDue.length === 0 && overdue.length === 0 && failed.length === 0;

    return (
        <AdminLayout title="Collections" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Collections"
                    description={`Workspaces failing to pay. Access is revoked ${graceDays} days after a subscription falls past due.`}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Overdue"
                        value={exposure.formatted}
                        icon={<CircleDollarSign className="size-4" aria-hidden="true" />}
                        footer={`${overdue.length} invoice${overdue.length === 1 ? '' : 's'} past their due date`}
                    />
                    <StatCard
                        label="Past due"
                        value={pastDue.length.toLocaleString()}
                        icon={<CalendarClock className="size-4" aria-hidden="true" />}
                        footer="Subscriptions inside the grace window"
                    />
                    <StatCard
                        label="Failed payments"
                        value={failed.length.toLocaleString()}
                        icon={<CircleAlert className="size-4" aria-hidden="true" />}
                        footer="Most recent 50"
                    />
                </div>

                {nothingToDo ? (
                    <EmptyState
                        icon={PartyPopper}
                        title="Nothing is overdue"
                        description="Every workspace is paid up. There is nothing to collect."
                    />
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Past-due subscriptions</CardTitle>
                                <CardDescription>Still served, but on a countdown.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {pastDue.length === 0 ? (
                                    <p className="px-5 py-6 text-sm text-muted-foreground">No subscriptions are past due.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <caption className="sr-only">Subscriptions past due, with days remaining before access is revoked</caption>
                                            <thead>
                                                <tr className="border-b border-border text-left text-muted-foreground">
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Workspace</th>
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Plan</th>
                                                    <th scope="col" className="px-5 py-2.5 text-right font-medium">MRR</th>
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Since</th>
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Grace</th>
                                                    {can.manage && <th scope="col" className="px-5 py-2.5" />}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {pastDue.map((row) => (
                                                    <tr key={row.id} className="border-b border-border last:border-0">
                                                        <td className="px-5 py-2.5 font-medium text-foreground">{row.company}</td>
                                                        <td className="px-5 py-2.5 text-muted-foreground">{row.plan}</td>
                                                        <td className="px-5 py-2.5 text-right tabular-nums">{row.mrr.formatted}</td>
                                                        <td className="px-5 py-2.5 text-muted-foreground">{relative(row.past_due_since)}</td>
                                                        <td className="px-5 py-2.5">
                                                            <GraceCountdown days={row.days_left} />
                                                        </td>
                                                        {can.manage && (
                                                            <td className="px-5 py-2.5 text-right">
                                                                <Button type="button" size="sm" variant="outline" onClick={() => setExtending(row)}>
                                                                    Extend grace
                                                                </Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Overdue invoices</CardTitle>
                                <CardDescription>Issued, due, and still unpaid.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {overdue.length === 0 ? (
                                    <p className="px-5 py-6 text-sm text-muted-foreground">No invoices are overdue.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <caption className="sr-only">Invoices past their due date</caption>
                                            <thead>
                                                <tr className="border-b border-border text-left text-muted-foreground">
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Invoice</th>
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Workspace</th>
                                                    <th scope="col" className="px-5 py-2.5 text-right font-medium">Total</th>
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Due</th>
                                                    <th scope="col" className="px-5 py-2.5 text-right font-medium">Overdue</th>
                                                    {can.manage && <th scope="col" className="px-5 py-2.5" />}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {overdue.map((row) => (
                                                    <tr key={row.id} className="border-b border-border last:border-0">
                                                        <td className="px-5 py-2.5 font-mono text-xs">{row.number}</td>
                                                        <td className="px-5 py-2.5 font-medium text-foreground">{row.company}</td>
                                                        <td className="px-5 py-2.5 text-right tabular-nums">{row.total.formatted}</td>
                                                        <td className="px-5 py-2.5 text-muted-foreground">{relative(row.due_at)}</td>
                                                        <td className={cn('px-5 py-2.5 text-right tabular-nums', row.days_overdue > 14 && 'text-destructive')}>
                                                            {row.days_overdue}d
                                                        </td>
                                                        {can.manage && (
                                                            <td className="px-5 py-2.5 text-right">
                                                                <Button type="button" size="sm" variant="outline" onClick={() => setSettling(row)}>
                                                                    <CheckCircle2 className="size-4" aria-hidden="true" />
                                                                    Mark paid
                                                                </Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {failed.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm">Failed payments</CardTitle>
                                    <CardDescription>What the processor refused, and why.</CardDescription>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <caption className="sr-only">Failed payment attempts</caption>
                                            <thead>
                                                <tr className="border-b border-border text-left text-muted-foreground">
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Workspace</th>
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Invoice</th>
                                                    <th scope="col" className="px-5 py-2.5 text-right font-medium">Amount</th>
                                                    <th scope="col" className="px-5 py-2.5 font-medium">Reason</th>
                                                    <th scope="col" className="px-5 py-2.5 font-medium">When</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {failed.map((row) => (
                                                    <tr key={row.id} className="border-b border-border last:border-0">
                                                        <td className="px-5 py-2.5 font-medium text-foreground">{row.company}</td>
                                                        <td className="px-5 py-2.5 font-mono text-xs">{row.invoice ?? '—'}</td>
                                                        <td className="px-5 py-2.5 text-right tabular-nums">{row.amount.formatted}</td>
                                                        <td className="px-5 py-2.5 text-muted-foreground">{row.reason ?? 'Not reported'}</td>
                                                        <td className="px-5 py-2.5 text-muted-foreground">{relative(row.failed_at)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}
            </div>

            <Dialog open={settling !== null} onOpenChange={(open) => !open && setSettling(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Mark {settling?.number} as paid</DialogTitle>
                        <DialogDescription>
                            Records a payment of {settling?.total.formatted} against this invoice and lifts the workspace out of
                            collections once nothing else is owed. This settles the ledger here — it does not charge a card.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="settle-reference">Reference</Label>
                        <Input
                            id="settle-reference"
                            value={reference}
                            placeholder="Bank transfer ref, cheque number…"
                            onChange={(event) => setReference(event.target.value)}
                        />
                        <p className="text-xs text-muted-foreground">Optional, but it is what makes the entry reconcilable later.</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setSettling(null)}>
                            Cancel
                        </Button>
                        <Button type="button" disabled={working} onClick={settle}>
                            {working ? 'Recording…' : 'Record payment'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={extending !== null} onOpenChange={(open) => !open && setExtending(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Extend grace for {extending?.company}</DialogTitle>
                        <DialogDescription>
                            Pushes the deadline back so the next scheduler run does not revoke access.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="grace-days">Extra days</Label>
                        <Input
                            id="grace-days"
                            type="number"
                            min={1}
                            max={60}
                            value={days}
                            onChange={(event) => setDays(event.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setExtending(null)}>
                            Cancel
                        </Button>
                        <Button type="button" disabled={working} onClick={extendGrace}>
                            {working ? 'Extending…' : 'Extend'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
