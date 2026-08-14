import { PageHeader } from '@/components/app-shell/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AdminLayout } from '@/layouts/admin-layout';
import { router } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { useState } from 'react';

interface AnnouncementRow {
    id: number;
    heading: string;
    message: string;
    level: string;
    audience: string;
    recipients_count: number;
    admin: string | null;
    sent_at: string | null;
}

interface AnnouncementsPageProps {
    history: AnnouncementRow[];
}

function formatWhen(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'd MMM yyyy HH:mm') : '—';
}

export default function AdminAnnouncements({ history }: AnnouncementsPageProps) {
    const [heading, setHeading] = useState('');
    const [message, setMessage] = useState('');
    const [level, setLevel] = useState('info');
    const [audience, setAudience] = useState('all');
    const [url, setUrl] = useState('');
    const [sending, setSending] = useState(false);

    function send(): void {
        setSending(true);
        router.post(
            route('admin.announcements.store'),
            {
                heading,
                message,
                level,
                audience,
                url: url || null,
            },
            {
                preserveScroll: true,
                onFinish: () => setSending(false),
                onSuccess: () => {
                    setHeading('');
                    setMessage('');
                    setUrl('');
                },
            },
        );
    }

    return (
        <AdminLayout title="Announcements" breadcrumbs={[{ label: 'System' }, { label: 'Announcements' }]}>
            <div className="mx-auto w-full max-w-4xl space-y-6">
                <PageHeader
                    title="Announcements"
                    description="Broadcast maintenance windows, billing notices, and product updates to tenant owners."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Compose</CardTitle>
                        <CardDescription>Delivered to workspace owners as in-app and email notifications.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="heading">Heading</Label>
                            <Input id="heading" value={heading} onChange={(event) => setHeading(event.target.value)} maxLength={180} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="message">Message</Label>
                            <Textarea id="message" value={message} onChange={(event) => setMessage(event.target.value)} rows={5} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label>Level</Label>
                                <Select value={level} onValueChange={setLevel}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="info">Info</SelectItem>
                                        <SelectItem value="success">Success</SelectItem>
                                        <SelectItem value="warning">Warning</SelectItem>
                                        <SelectItem value="critical">Critical</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label>Audience</Label>
                                <Select value={audience} onValueChange={setAudience}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All tenants</SelectItem>
                                        <SelectItem value="active">Active only</SelectItem>
                                        <SelectItem value="suspended">Suspended only</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="url">Optional link</Label>
                            <Input
                                id="url"
                                type="url"
                                value={url}
                                onChange={(event) => setUrl(event.target.value)}
                                placeholder="https://"
                            />
                        </div>
                        <Button onClick={send} disabled={sending || !heading.trim() || !message.trim()}>
                            {sending ? 'Sending…' : 'Send announcement'}
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent broadcasts</CardTitle>
                        <CardDescription>Last 30 announcements sent from the console.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {history.length === 0 ? (
                            <p className="px-6 pb-6 text-sm text-muted-foreground">No announcements sent yet.</p>
                        ) : (
                            <div className="divide-y divide-border">
                                {history.map((row) => (
                                    <div key={row.id} className="space-y-2 px-6 py-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">{row.heading}</p>
                                            <Badge variant="outline">{row.level}</Badge>
                                            <Badge variant="secondary">{row.audience}</Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground whitespace-pre-wrap">{row.message}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {row.admin ?? 'Operator'} · {formatWhen(row.sent_at)} · {row.recipients_count} recipient(s)
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
