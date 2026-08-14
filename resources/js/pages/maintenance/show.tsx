import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { MaintenanceRequestRow, OptionMap } from '@/types/operations';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Pencil, Play, XCircle } from 'lucide-react';
import { useState } from 'react';

const NONE = '__none__';

interface Props {
    workOrder: MaintenanceRequestRow;
    staff: OptionMap;
    can: { update: boolean; assign: boolean; complete: boolean };
}

export default function MaintenanceShow({ workOrder, staff, can }: Props) {
    const confirm = useConfirm();
    const [showComplete, setShowComplete] = useState(false);
    const assignForm = useForm({ assigned_to: String(workOrder.assigned_to ?? '') });
    const completeForm = useForm({ resolution_notes: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Maintenance', href: route('maintenance.index') },
        { label: workOrder.number },
    ];

    function submitAssign(event: React.FormEvent): void {
        event.preventDefault();
        assignForm.transform((v) => ({ assigned_to: v.assigned_to === '' ? null : v.assigned_to }));
        assignForm.post(route('maintenance.assign', workOrder.id), { preserveScroll: true });
    }

    function submitComplete(event: React.FormEvent): void {
        event.preventDefault();
        completeForm.post(route('maintenance.complete', workOrder.id), { preserveScroll: true, onSuccess: () => setShowComplete(false) });
    }

    async function cancelOrder(): Promise<void> {
        const ok = await confirm({ title: 'Cancel this work order?', variant: 'destructive', confirmLabel: 'Cancel' });
        if (ok) router.post(route('maintenance.cancel', workOrder.id), {}, { preserveScroll: true });
    }

    return (
        <AppLayout title={workOrder.number} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={workOrder.number}
                    description={workOrder.title}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={route('maintenance.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back
                                </Link>
                            </Button>
                            {can.update && workOrder.is_open && (
                                <Button asChild variant="outline">
                                    <Link href={route('maintenance.edit', workOrder.id)}>
                                        <Pencil className="size-4" aria-hidden="true" />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                            {can.update && workOrder.is_open && workOrder.status === 'open' && (
                                <Button onClick={() => router.post(route('maintenance.start', workOrder.id), {}, { preserveScroll: true })}>
                                    <Play className="size-4" aria-hidden="true" />
                                    Start work
                                </Button>
                            )}
                            {can.complete && workOrder.is_open && (
                                <Button onClick={() => setShowComplete((v) => !v)}>
                                    <Check className="size-4" aria-hidden="true" />
                                    Complete
                                </Button>
                            )}
                            {can.update && workOrder.is_open && (
                                <Button variant="destructive" onClick={() => void cancelOrder()}>
                                    <XCircle className="size-4" aria-hidden="true" />
                                    Cancel
                                </Button>
                            )}
                        </div>
                    }
                />

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Details</CardTitle>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">{workOrder.status_label}</Badge>
                            <Badge variant="outline">{workOrder.priority_label}</Badge>
                            {workOrder.blocks_room && <Badge variant="outline">Blocks booking</Badge>}
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground">Hotel</p>
                            <p>{workOrder.hotel ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Room / Bed</p>
                            <p>{workOrder.room ?? workOrder.bed ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Category</p>
                            <p>{workOrder.category_label}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Reporter</p>
                            <p>{workOrder.reporter ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Assignee</p>
                            <p>{workOrder.assignee ?? 'Unassigned'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Due</p>
                            <p>{workOrder.due_at ? new Date(workOrder.due_at).toLocaleDateString() : '—'}</p>
                        </div>
                        {workOrder.description && (
                            <div className="sm:col-span-2">
                                <p className="text-muted-foreground">Description</p>
                                <p>{workOrder.description}</p>
                            </div>
                        )}
                        {workOrder.resolution_notes && (
                            <div className="sm:col-span-2">
                                <p className="text-muted-foreground">Resolution</p>
                                <p>{workOrder.resolution_notes}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {can.assign && workOrder.is_open && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Assign</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitAssign} className="flex flex-wrap items-end gap-4">
                                <div className="min-w-[14rem] flex-1 space-y-2">
                                    <Label>Technician</Label>
                                    <Select value={assignForm.data.assigned_to || NONE} onValueChange={(v) => assignForm.setData('assigned_to', v === NONE ? '' : v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Unassigned" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>Unassigned</SelectItem>
                                            {Object.entries(staff).map(([id, name]) => (
                                                <SelectItem key={id} value={id}>
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button type="submit" disabled={assignForm.processing}>
                                    Save assignment
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {showComplete && can.complete && workOrder.is_open && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Complete work order</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitComplete} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="resolution_notes">Resolution notes</Label>
                                    <Textarea id="resolution_notes" rows={3} value={completeForm.data.resolution_notes} onChange={(e) => completeForm.setData('resolution_notes', e.target.value)} />
                                </div>
                                <Button type="submit" disabled={completeForm.processing}>
                                    Confirm completion
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
