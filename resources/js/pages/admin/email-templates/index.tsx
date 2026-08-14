import { PageHeader } from '@/components/app-shell/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { AdminLayout } from '@/layouts/admin-layout';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface EmailTemplateRow {
    id: number;
    key: string;
    name: string;
    subject: string;
    body: string;
    placeholders: string[];
    is_active: boolean;
}

interface EmailTemplatesPageProps {
    templates: EmailTemplateRow[];
}

export default function AdminEmailTemplates({ templates }: EmailTemplatesPageProps) {
    const [selectedId, setSelectedId] = useState(templates[0]?.id ?? null);
    const selected = templates.find((template) => template.id === selectedId) ?? templates[0] ?? null;
    const [subject, setSubject] = useState(selected?.subject ?? '');
    const [body, setBody] = useState(selected?.body ?? '');
    const [isActive, setIsActive] = useState(selected?.is_active ?? true);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!selected) {
            return;
        }

        setSubject(selected.subject);
        setBody(selected.body);
        setIsActive(selected.is_active);
    }, [selected?.id, selected?.subject, selected?.body, selected?.is_active]);

    function save(): void {
        if (!selected) {
            return;
        }

        setSaving(true);
        router.put(
            route('admin.email-templates.update', selected.id),
            { subject, body, is_active: isActive },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    }

    return (
        <AdminLayout title="Email templates" breadcrumbs={[{ label: 'System' }, { label: 'Email templates' }]}>
            <div className="mx-auto w-full max-w-5xl space-y-6">
                <PageHeader
                    title="Email templates"
                    description="Lifecycle copy for welcome, trial ending, past due, cancellation, and suspension."
                />

                <div className="grid gap-4 lg:grid-cols-[220px_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Templates</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 p-2">
                            {templates.map((template) => (
                                <button
                                    key={template.id}
                                    type="button"
                                    onClick={() => setSelectedId(template.id)}
                                    className={`flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm ${
                                        selected?.id === template.id ? 'bg-muted font-medium' : 'hover:bg-muted/60'
                                    }`}
                                >
                                    <span>{template.name}</span>
                                    {!template.is_active && <Badge variant="outline">Off</Badge>}
                                </button>
                            ))}
                        </CardContent>
                    </Card>

                    {selected ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>{selected.name}</CardTitle>
                                <CardDescription>
                                    Key <code className="text-xs">{selected.key}</code>
                                    {selected.placeholders.length > 0 && (
                                        <> · Placeholders: {selected.placeholders.map((item) => `{{${item}}}`).join(', ')}</>
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
                                    <div>
                                        <p className="font-medium">Active</p>
                                        <p className="text-sm text-muted-foreground">Inactive templates fall back to hardcoded copy.</p>
                                    </div>
                                    <Switch checked={isActive} onCheckedChange={setIsActive} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="subject">Subject</Label>
                                    <Input id="subject" value={subject} onChange={(event) => setSubject(event.target.value)} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="body">Body</Label>
                                    <Textarea id="body" value={body} onChange={(event) => setBody(event.target.value)} rows={12} />
                                </div>
                                <Button onClick={save} disabled={saving}>
                                    {saving ? 'Saving…' : 'Save template'}
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent className="py-10 text-sm text-muted-foreground">No templates seeded yet.</CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
