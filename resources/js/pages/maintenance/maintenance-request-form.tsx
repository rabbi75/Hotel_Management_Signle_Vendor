import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { FormActions } from '@/components/forms/form-actions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { EnumOption, MaintenanceRequestRow } from '@/types/operations';
import type { OptionMap } from '@/types/hotel';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';

const NONE = '__none__';

interface Props {
    workOrder?: MaintenanceRequestRow;
    hotels: OptionMap;
    rooms: OptionMap;
    beds: OptionMap;
    staff: OptionMap;
    categories: EnumOption[];
    priorities: EnumOption[];
    defaultHotelId?: number | null;
}

export function MaintenanceRequestForm({ workOrder, hotels, rooms, beds, staff, categories, priorities, defaultHotelId }: Props) {
    const editing = workOrder !== undefined;
    const form = useForm({
        hotel_id: String(workOrder?.hotel_id ?? defaultHotelId ?? ''),
        room_id: String(workOrder?.room_id ?? ''),
        bed_id: String(workOrder?.bed_id ?? ''),
        title: workOrder?.title ?? '',
        description: workOrder?.description ?? '',
        category: workOrder?.category ?? 'other',
        priority: workOrder?.priority ?? 'normal',
        blocks_room: workOrder?.blocks_room ?? true,
        assigned_to: String(workOrder?.assigned_to ?? ''),
        due_at: workOrder?.due_at ? workOrder.due_at.slice(0, 10) : '',
    });
    const { data, setData, errors, processing, isDirty, recentlySuccessful } = form;

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            room_id: values.room_id === '' ? null : values.room_id,
            bed_id: values.bed_id === '' ? null : values.bed_id,
            assigned_to: values.assigned_to === '' ? null : values.assigned_to,
            due_at: values.due_at === '' ? null : values.due_at,
        }));
        if (editing && workOrder) {
            form.put(route('maintenance.update', workOrder.id), { preserveScroll: true });
            return;
        }
        form.post(route('maintenance.store'));
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
                    <CardTitle>Work order</CardTitle>
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
                        <Label htmlFor="title">Title *</Label>
                        <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Room</Label>
                            <Select value={data.room_id || NONE} onValueChange={(v) => setData('room_id', v === NONE ? '' : v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Optional" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>None</SelectItem>
                                    {Object.entries(rooms).map(([id, name]) => (
                                        <SelectItem key={id} value={id}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Bed</Label>
                            <Select value={data.bed_id || NONE} onValueChange={(v) => setData('bed_id', v === NONE ? '' : v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Optional" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>None</SelectItem>
                                    {Object.entries(beds).map(([id, name]) => (
                                        <SelectItem key={id} value={id}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Category</Label>
                            <Select value={data.category} onValueChange={(v) => setData('category', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((c) => (
                                        <SelectItem key={c.value} value={c.value}>
                                            {c.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
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
                    </div>
                    <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                        <div>
                            <Label htmlFor="blocks_room">Block room from booking</Label>
                            <p className="text-xs text-muted-foreground">Sets room status to Maintenance while open.</p>
                        </div>
                        <Switch id="blocks_room" checked={data.blocks_room} onCheckedChange={(v) => setData('blocks_room', v)} />
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
                        <Label htmlFor="due_at">Due date</Label>
                        <Input id="due_at" type="date" value={data.due_at} onChange={(e) => setData('due_at', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label htmlFor="description">Description</Label>
                            <AiAssistButton
                                action="maintenance.triage"
                                label="Triage with AI"
                                subjectId={workOrder?.id}
                                draft={{
                                    title: data.title,
                                    description: data.description,
                                    category: data.category,
                                    priority: data.priority,
                                    blocks_room: data.blocks_room,
                                    room_label: rooms[data.room_id],
                                }}
                                onApply={(text) => {
                                    const titleMatch = text.match(/Suggested title:\s*(.+)/i);
                                    if (titleMatch?.[1] && !data.title.trim()) {
                                        setData('title', titleMatch[1].trim());
                                    }
                                    setData('description', data.description.trim() ? `${data.description.trim()}\n\n${text}` : text);
                                }}
                                applyLabel="Append triage notes"
                                description="Suggest category, priority, and whether to block the room."
                            />
                        </div>
                        <Textarea id="description" rows={4} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </div>
                </CardContent>
            </Card>
            <FormActions
                dirty={isDirty}
                submitting={processing}
                saved={recentlySuccessful}
                submitLabel={editing ? 'Save work order' : 'Create work order'}
                onCancel={() => form.reset()}
            />
        </form>
    );
}
