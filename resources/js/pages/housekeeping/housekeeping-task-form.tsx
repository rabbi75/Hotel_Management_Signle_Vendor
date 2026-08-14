import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { EnumOption } from '@/types/operations';
import type { OptionMap } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    hotels: OptionMap;
    rooms: OptionMap;
    staff: OptionMap;
    priorities: EnumOption[];
    taskTypes: EnumOption[];
    defaultHotelId?: number | null;
}

export function HousekeepingTaskForm({ hotels, rooms, staff, priorities, taskTypes, defaultHotelId }: Props) {
    const form = useForm({
        hotel_id: String(defaultHotelId ?? ''),
        room_id: '',
        priority: 'normal',
        task_type: 'other',
        assigned_to: '',
        instructions: '',
        scheduled_for: '',
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            assigned_to: values.assigned_to === '' ? null : values.assigned_to,
            scheduled_for: values.scheduled_for === '' ? null : values.scheduled_for,
        }));
        form.post(route('housekeeping.store'));
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>Could not save</AlertTitle>
                    <AlertDescription>Check the highlighted fields.</AlertDescription>
                </Alert>
            )}
            <Card>
                <CardHeader>
                    <CardTitle>Task details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="space-y-2">
                        <Label>Hotel *</Label>
                        <Select value={data.hotel_id || NONE} onValueChange={(v) => setData('hotel_id', v === NONE ? '' : v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select hotel" />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(hotels).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Room *</Label>
                        <Select value={data.room_id || NONE} onValueChange={(v) => setData('room_id', v === NONE ? '' : v)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select room" />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(rooms).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Priority</Label>
                            <Select value={data.priority} onValueChange={(v) => setData('priority', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {priorities.map((p) => (
                                        <SelectItem key={p.value} value={p.value}>
                                            {p.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Type</Label>
                            <Select value={data.task_type} onValueChange={(v) => setData('task_type', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {taskTypes.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Assign to</Label>
                        <Select value={data.assigned_to || NONE} onValueChange={(v) => setData('assigned_to', v === NONE ? '' : v)}>
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
                    <div className="space-y-2">
                        <Label htmlFor="scheduled_for">Scheduled for</Label>
                        <Input id="scheduled_for" type="date" value={data.scheduled_for} onChange={(e) => setData('scheduled_for', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="instructions">Instructions</Label>
                        <Textarea id="instructions" rows={3} value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} />
                    </div>
                </CardContent>
            </Card>
            <FormActions dirty={isDirty} submitting={processing} saved={recentlySuccessful} submitLabel="Create task" onCancel={() => form.reset()} />
        </form>
    );
}
