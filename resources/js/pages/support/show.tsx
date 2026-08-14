import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';

interface TicketMessage {
    id: number;
    body: string;
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
    priority_label: string;
    priority_color: string;
    category_label: string;
    messages: TicketMessage[];
}

interface Props {
    ticket: Ticket;
    can: { reply: boolean; close: boolean };
}

const COLOR: Record<string, NonNullable<BadgeProps['variant']>> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    danger: 'destructive',
    neutral: 'secondary',
};

function when(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'd MMM yyyy HH:mm') : '';
}

export default function SupportShow({ ticket, can }: Props) {
    const form = useForm({ body: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Support', href: route('support.index') },
        { label: ticket.number },
    ];

    function reply(event: FormEvent): void {
        event.preventDefault();
        form.post(route('support.reply', ticket.uuid), {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    }

    return (
        <AppLayout title={ticket.number} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={ticket.number}
                    description={ticket.subject}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={route('support.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back
                                </Link>
                            </Button>
                            {can.close && ticket.status !== 'closed' && (
                                <Button variant="outline" onClick={() => router.post(route('support.close', ticket.uuid), {}, { preserveScroll: true })}>
                                    Close ticket
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <Badge variant={COLOR[ticket.status_color] ?? 'secondary'}>{ticket.status_label}</Badge>
                    <Badge variant={COLOR[ticket.priority_color] ?? 'outline'}>{ticket.priority_label}</Badge>
                    <Badge variant="outline">{ticket.category_label}</Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Conversation</CardTitle>
                        <CardDescription>Replies from platform support appear here.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {ticket.messages.map((message) => (
                            <div
                                key={message.id}
                                className={cn(
                                    'rounded-lg border border-border p-4',
                                    message.author_type === 'support' && 'bg-muted/40',
                                )}
                            >
                                <div className="mb-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    <span className="font-medium text-foreground">{message.author}</span>
                                    <Badge variant="outline">{message.author_type === 'support' ? 'Support' : 'You'}</Badge>
                                    <span>{when(message.created_at)}</span>
                                </div>
                                <p className="whitespace-pre-wrap text-sm">{message.body}</p>
                            </div>
                        ))}

                        {can.reply && (
                            <form className="space-y-3 border-t border-border pt-4" onSubmit={reply}>
                                <Textarea
                                    rows={4}
                                    value={form.data.body}
                                    onChange={(event) => form.setData('body', event.target.value)}
                                    placeholder="Write a reply…"
                                    required
                                />
                                <Button type="submit" disabled={form.processing || !form.data.body.trim()}>
                                    {form.processing ? 'Sending…' : 'Send reply'}
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
