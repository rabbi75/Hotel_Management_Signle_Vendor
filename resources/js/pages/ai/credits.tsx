import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { RelativeDateCell, TextCell } from '@/components/data-table/data-table-cells';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AiCreditTransactionRow, AiCreditsPageProps } from '@/types/ai';
import { useForm } from '@inertiajs/react';
import { Coins, Info, SearchX, SlidersHorizontal } from 'lucide-react';
import { useId, useState, type FormEvent } from 'react';

const TYPE_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    success: 'success',
    warning: 'warning',
    info: 'info',
    muted: 'secondary',
};

interface AdjustForm {
    credits: number;
    reason: string;
}

export default function AiCredits({ balance, rates, ledger, can }: AiCreditsPageProps) {
    const { can: allows } = usePermissions();
    const creditsId = useId();
    const reasonId = useId();

    const [open, setOpen] = useState(false);

    const mayManage = can.manage && allows('ai.credits.manage');
    const filtered = Boolean(ledger.state.search) || Object.keys(ledger.state.filters).length > 0;

    const committed = balance.used + balance.reserved;
    const usedPercent = balance.allowance > 0 ? Math.min(100, Math.round((committed / balance.allowance) * 100)) : 0;

    const form = useForm<AdjustForm>({ credits: 100, reason: '' });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.post(route('ai.credits.adjust'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    const columns: ColumnRenderers<AiCreditTransactionRow> = {
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
        type_label: (row) => <Badge variant={TYPE_VARIANT[row.type_color] ?? 'secondary'}>{row.type_label}</Badge>,
        credits: (row) => (
            <span className="tabular-nums">
                {row.credits > 0 ? '+' : ''}
                {row.credits}
            </span>
        ),
        description: (row) => <TextCell value={row.description} muted />,
        user: (row) => <TextCell value={row.user} muted />,
        period: (row) => <TextCell value={row.period} muted className="font-mono text-xs" />,
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'AI', href: routeUrl('ai.index') ?? undefined },
        { label: 'Credits' },
    ];

    return (
        <AppLayout title="AI credits" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="AI credits"
                    description={`Allowance and spend for ${balance.period}.`}
                    actions={
                        mayManage ? (
                            <Button type="button" onClick={() => setOpen(true)}>
                                <SlidersHorizontal className="size-4" aria-hidden="true" />
                                Adjust allowance
                            </Button>
                        ) : null
                    }
                />

                {!balance.enabled && (
                    <Alert>
                        <Info aria-hidden="true" />
                        <AlertDescription>
                            Credit metering is disabled on this installation, so generations are never refused for lack of credits.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardDescription>Allowance</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">{balance.allowance}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Used</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">{balance.used}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Held for in-flight runs</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">{balance.reserved}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Available</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">{balance.available}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>This month</CardTitle>
                        <CardDescription>
                            {rates.per_1k_input} credit(s) per 1,000 input tokens, {rates.per_1k_output} per 1,000 output tokens.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Progress value={usedPercent} aria-label={`${committed} of ${balance.allowance} credits committed`} />
                        <p className="text-sm text-muted-foreground">
                            {committed} of {balance.allowance} credits committed ({usedPercent}%). Held credits are released automatically
                            when a generation fails.
                        </p>
                    </CardContent>
                </Card>

                <DataTable<AiCreditTransactionRow>
                    payload={ledger}
                    propKey="ledger"
                    name="ledger"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder="Search the ledger…"
                    caption="AI credit ledger"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No entries match these filters"
                                description="Clear the type filter to see the whole ledger."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={Coins}
                                title="Nothing recorded yet"
                                description="Reservations, charges and refunds are listed here as they happen."
                                className="border-0"
                            />
                        )
                    }
                />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Adjust the allowance</DialogTitle>
                        <DialogDescription>
                            Applies to {balance.period} only. Use a negative number to reduce it. Every adjustment is recorded in the ledger
                            and the security log.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} noValidate className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor={creditsId}>Credits</Label>
                            <Input
                                id={creditsId}
                                type="number"
                                value={form.data.credits}
                                required
                                aria-invalid={Boolean(form.errors.credits)}
                                onChange={(event) => form.setData('credits', Number(event.target.value))}
                            />
                            {form.errors.credits && <p className="text-sm font-medium text-destructive">{form.errors.credits}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={reasonId}>Reason</Label>
                            <Input
                                id={reasonId}
                                value={form.data.reason}
                                required
                                placeholder="Annual top-up"
                                aria-invalid={Boolean(form.errors.reason)}
                                onChange={(event) => form.setData('reason', event.target.value)}
                            />
                            {form.errors.reason && <p className="text-sm font-medium text-destructive">{form.errors.reason}</p>}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner className="size-4" aria-hidden="true" />}
                                Apply adjustment
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
