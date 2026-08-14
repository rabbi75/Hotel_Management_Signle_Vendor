import { PageHeader } from '@/components/app-shell/page-header';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { AdminLayout } from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { Link, router, useForm } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { ArrowLeft } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface EnumOption {
    value: string;
    label: string;
}

interface TicketMessage {
    id: number;
    body: string;
    is_internal: boolean;
    author: string;
    author_type: 'support' | 'customer';
    created_at: string | null;
}

interface Ticket {
    uuid: string;
    number: string;
    subject: string;
    status: string;
    status_label: string;
    status_color: string;
    priority: string;
    priority_label: string;
    priority_color: string;
    category: string;
    category_label: string;
    company: string | null;
    company_uuid: string | null;
    requester: string | null;
    requester_email: string | null;
    assigned_admin_id: number | null;
    messages: TicketMessage[];
}

interface Props {
    ticket: Ticket;
    admins: Record<string, string>;
    statuses: EnumOption[];
    priorities: EnumOption[];
    categories: EnumOption[];
    can: { manage: boolean };
}

const COLOR: Record<string, NonNullable<BadgeProps['variant']>> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    danger: 'destructive',
    neutral: 'secondary',
};

const NONE = '__none__';

function when(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'd MMM yyyy HH:mm') : '';
}

export default function AdminTicketShow({ ticket, admins, statuses, priorities, categories, can }: Props) {
    const replyForm = useForm({ body: '', is_internal: false });
    const [status, setStatus] = useState(ticket.status);
    const [priority, setPriority] = useState(ticket.priority);
    const [category, setCategory] = useState(ticket.category);
    const [assignee, setAssignee] = useState(ticket.assigned_admin_id ? String(ticket.assigned_admin_id) : NONE);

    function saveMeta(): void {
        router.patch(
            route('admin.tickets.update', ticket.uuid),
            {
                status,
                priority,
                category,
                assigned_admin_id: assignee === NONE ? null : Number(assignee),
            },
            { preserveScroll: true },
        );
    }

    function reply(event: FormEvent): void {
        event.preventDefault();
        replyForm.post(route('admin.tickets.reply', ticket.uuid), {
            preserveScroll: true,
            onSuccess: () => replyForm.reset('body'),
        });
    }

    return (
        <AdminLayout
            title={ticket.number}
            breadcrumbs={[
                { label: 'Tickets', href: route('admin.tickets.index') },
                { label: ticket.number },
            ]}
        >
            <div className="mx-auto w-full max-w-5xl space-y-6">
                <PageHeader
                    title={ticket.number}
                    description={ticket.subject}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('admin.tickets.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Inbox
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <Badge variant={COLOR[ticket.status_color] ?? 'secondary'}>{ticket.status_label}</Badge>
                    <Badge variant={COLOR[ticket.priority_color] ?? 'outline'}>{ticket.priority_label}</Badge>
                    <Badge variant="outline">{ticket.category_label}</Badge>
                    {ticket.company_uuid && (
                        <Button asChild variant="link" className="h-auto p-0">
                            <Link href={route('admin.tenants.show', ticket.company_uuid)}>{ticket.company}</Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Thread</CardTitle>
                            <CardDescription>
                                {ticket.requester}
                                {ticket.requester_email ? ` · ${ticket.requester_email}` : ''}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {ticket.messages.map((message) => (
                                <div
                                    key={message.id}
                                    className={cn(
                                        'rounded-lg border border-border p-4',
                                        message.is_internal && 'border-dashed bg-amber-50/50 dark:bg-amber-950/20',
                                        message.author_type === 'support' && !message.is_internal && 'bg-muted/40',
                                    )}
                                >
                                    <div className="mb-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span className="font-medium text-foreground">{message.author}</span>
                                        <Badge variant="outline">
                                            {message.is_internal ? 'Internal' : message.author_type === 'support' ? 'Support' : 'Customer'}
                                        </Badge>
                                        <span>{when(message.created_at)}</span>
                                    </div>
                                    <p className="whitespace-pre-wrap text-sm">{message.body}</p>
                                </div>
                            ))}

                            {can.manage && (
                                <form className="space-y-3 border-t border-border pt-4" onSubmit={reply}>
                                    <Textarea
                                        rows={4}
                                        value={replyForm.data.body}
                                        onChange={(event) => replyForm.setData('body', event.target.value)}
                                        placeholder="Write a reply…"
                                        required
                                    />
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <label className="flex items-center gap-2 text-sm">
                                            <Switch
                                                checked={replyForm.data.is_internal}
                                                onCheckedChange={(checked) => replyForm.setData('is_internal', checked)}
                                            />
                                            Internal note (hidden from tenant)
                                        </label>
                                        <Button type="submit" disabled={replyForm.processing || !replyForm.data.body.trim()}>
                                            {replyForm.processing ? 'Posting…' : 'Post reply'}
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </CardContent>
                    </Card>

                    {can.manage && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Triage</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label>Status</Label>
                                    <Select value={status} onValueChange={setStatus}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Priority</Label>
                                    <Select value={priority} onValueChange={setPriority}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {priorities.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Category</Label>
                                    <Select value={category} onValueChange={setCategory}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Assignee</Label>
                                    <Select value={assignee} onValueChange={setAssignee}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Unassigned" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>Unassigned</SelectItem>
                                            {Object.entries(admins).map(([id, name]) => (
                                                <SelectItem key={id} value={id}>
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button className="w-full" onClick={saveMeta}>
                                    Save changes
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
