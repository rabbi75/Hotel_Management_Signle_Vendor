import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { HousekeepingTaskRow, OptionMap } from '@/types/operations';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Play, XCircle } from 'lucide-react';

const NONE = '__none__';

interface Props {
    task: HousekeepingTaskRow;
    staff: OptionMap;
    can: { manage: boolean; assign: boolean; complete: boolean };
}

export default function HousekeepingShow({ task, staff, can }: Props) {
    const confirm = useConfirm();
    const assignForm = useForm({ assigned_to: String(task.assigned_to ?? '') });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Housekeeping', href: route('housekeeping.index') },
        { label: task.number },
    ];

    function submitAssign(event: React.FormEvent): void {
        event.preventDefault();
        assignForm.transform((v) => ({ assigned_to: v.assigned_to === '' ? null : v.assigned_to }));
        assignForm.post(route('housekeeping.assign', task.id), { preserveScroll: true });
    }

    async function cancelTask(): Promise<void> {
        const ok = await confirm({ title: 'Cancel this task?', variant: 'destructive', confirmLabel: 'Cancel task' });
        if (ok) router.post(route('housekeeping.cancel', task.id), {}, { preserveScroll: true });
    }

    return (
        <AppLayout title={task.number} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={task.number}
                    description={`Room ${task.room ?? '—'} · ${task.hotel ?? 'Hotel'}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={route('housekeeping.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back
                                </Link>
                            </Button>
                            {can.manage && task.can_start && (
                                <Button onClick={() => router.post(route('housekeeping.start', task.id), {}, { preserveScroll: true })}>
                                    <Play className="size-4" aria-hidden="true" />
                                    Start cleaning
                                </Button>
                            )}
                            {can.complete && task.can_complete && (
                                <Button onClick={() => router.post(route('housekeeping.complete', task.id), {}, { preserveScroll: true })}>
                                    <Check className="size-4" aria-hidden="true" />
                                    Mark clean
                                </Button>
                            )}
                            {can.manage && task.can_cancel && (
                                <Button variant="destructive" onClick={() => void cancelTask()}>
                                    <XCircle className="size-4" aria-hidden="true" />
                                    Cancel
                                </Button>
                            )}
                        </div>
                    }
                />

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Overview</CardTitle>
                        <div className="flex gap-2">
                            <Badge variant="outline">{task.status_label}</Badge>
                            <Badge variant="outline">{task.priority_label}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground">Type</p>
                            <p>{task.task_type_label}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Reservation</p>
                            <p>{task.reservation ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Scheduled</p>
                            <p>{task.scheduled_for ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Assignee</p>
                            <p>{task.assignee ?? 'Unassigned'}</p>
                        </div>
                        {task.instructions && (
                            <div className="sm:col-span-2">
                                <p className="text-muted-foreground">Instructions</p>
                                <p>{task.instructions}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {can.assign && task.is_open && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Assign</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitAssign} className="flex flex-wrap items-end gap-4">
                                <div className="min-w-[14rem] flex-1 space-y-2">
                                    <Label>Staff member</Label>
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
            </div>
        </AppLayout>
    );
}
