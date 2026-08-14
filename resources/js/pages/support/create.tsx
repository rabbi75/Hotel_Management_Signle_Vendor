import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';

interface EnumOption {
    value: string;
    label: string;
}

interface Props {
    priorities: EnumOption[];
    categories: EnumOption[];
}

export default function SupportCreate({ priorities, categories }: Props) {
    const form = useForm({
        subject: '',
        body: '',
        priority: 'normal',
        category: 'other',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Support', href: route('support.index') },
        { label: 'New ticket' },
    ];

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post(route('support.store'));
    }

    return (
        <AppLayout title="New support ticket" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="New ticket"
                    description="Describe the issue — the platform team will reply here."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('support.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submit}>
                            <div className="space-y-1.5">
                                <Label htmlFor="subject">Subject</Label>
                                <Input
                                    id="subject"
                                    value={form.data.subject}
                                    onChange={(event) => form.setData('subject', event.target.value)}
                                    required
                                />
                                {form.errors.subject && <p className="text-sm text-destructive">{form.errors.subject}</p>}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label>Category</Label>
                                    <Select value={form.data.category} onValueChange={(value) => form.setData('category', value)}>
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
                                    <Label>Priority</Label>
                                    <Select value={form.data.priority} onValueChange={(value) => form.setData('priority', value)}>
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
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="body">Message</Label>
                                <Textarea
                                    id="body"
                                    rows={8}
                                    value={form.data.body}
                                    onChange={(event) => form.setData('body', event.target.value)}
                                    required
                                />
                                {form.errors.body && <p className="text-sm text-destructive">{form.errors.body}</p>}
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Opening…' : 'Open ticket'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
