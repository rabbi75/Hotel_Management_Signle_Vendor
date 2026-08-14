import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { DataTable, type ColumnRenderers } from '@/components/data-table/data-table';
import { BooleanCell, RelativeDateCell, TextCell, type RowAction } from '@/components/data-table/data-table-cells';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AiTemplateRow, AiTemplatesPageProps, AiVariableType } from '@/types/ai';
import { router, useForm } from '@inertiajs/react';
import { FileText, Pencil, Plus, SearchX, Trash2, Wand2, X } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';

interface VariableDraft {
    name: string;
    label: string;
    type: AiVariableType;
    required: boolean;
}

interface TemplateForm {
    name: string;
    description: string;
    category: string;
    prompt: string;
    variables: VariableDraft[];
    provider: string;
    model: string;
    is_shared: boolean;
}

const VARIABLE_TYPES: AiVariableType[] = ['text', 'textarea', 'number', 'select'];

const EMPTY: TemplateForm = {
    name: '',
    description: '',
    category: '',
    prompt: '',
    variables: [],
    provider: '',
    model: '',
    is_shared: false,
};

/** `{{variable}}` names the prompt body actually references. */
function placeholdersIn(prompt: string): string[] {
    const found = prompt.match(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g) ?? [];

    return [...new Set(found.map((match) => match.replace(/[{}\s]/g, '')))];
}

export default function AiTemplatesIndex({ table, providers, can }: AiTemplatesPageProps) {
    const { can: allows } = usePermissions();
    const confirm = useConfirm();

    const [editing, setEditing] = useState<AiTemplateRow | null>(null);
    const [open, setOpen] = useState(false);

    const mayManage = can.manage && allows('ai.templates.manage');
    const filtered = Boolean(table.state.search) || Object.keys(table.state.filters).length > 0;

    const form = useForm<TemplateForm>({ ...EMPTY });

    const declared = useMemo(() => new Set(form.data.variables.map((variable) => variable.name)), [form.data.variables]);
    const undeclared = placeholdersIn(form.data.prompt).filter((name) => !declared.has(name));

    function openCreate(): void {
        setEditing(null);
        form.setData({ ...EMPTY });
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(row: AiTemplateRow): void {
        setEditing(row);
        form.setData({
            name: row.name,
            description: row.description ?? '',
            category: row.category ?? '',
            prompt: row.prompt,
            variables: row.variables.map((variable) => ({ ...variable })),
            provider: row.provider ?? '',
            model: row.model ?? '',
            is_shared: row.is_shared,
        });
        form.clearErrors();
        setOpen(true);
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        };

        if (editing) {
            form.patch(route('ai.templates.update', editing.id), options);

            return;
        }

        form.post(route('ai.templates.store'), options);
    }

    function addVariable(name = ''): void {
        form.setData('variables', [
            ...form.data.variables,
            { name, label: name === '' ? '' : name.replace(/_/g, ' '), type: 'text', required: true },
        ]);
    }

    function updateVariable(index: number, patch: Partial<VariableDraft>): void {
        form.setData(
            'variables',
            form.data.variables.map((variable, position) => (position === index ? { ...variable, ...patch } : variable)),
        );
    }

    function removeVariable(index: number): void {
        form.setData(
            'variables',
            form.data.variables.filter((_, position) => position !== index),
        );
    }

    async function remove(row: AiTemplateRow): Promise<void> {
        const ok = await confirm({
            title: `Delete the “${row.name}” template?`,
            description: 'Generations that used it keep their history, but the template itself is gone.',
            variant: 'destructive',
            confirmLabel: 'Delete template',
        });

        if (ok) {
            router.delete(route('ai.templates.destroy', row.id), { preserveScroll: true });
        }
    }

    const columns: ColumnRenderers<AiTemplateRow> = {
        name: (row) => (
            <div className="min-w-0">
                <span className="block truncate text-sm font-medium">{row.name}</span>
                {row.description && <span className="block truncate text-xs text-muted-foreground">{row.description}</span>}
            </div>
        ),
        category: (row) => (row.category ? <Badge variant="outline">{row.category}</Badge> : <TextCell value="—" muted />),
        provider: (row) => <TextCell value={row.provider ?? 'Workspace default'} muted />,
        usage_count: (row) => <span className="tabular-nums">{row.usage_count}</span>,
        is_shared: (row) => <BooleanCell value={row.is_shared} trueLabel="Shared" falseLabel="Private" />,
        created_at: (row) => <RelativeDateCell value={row.created_at} />,
    };

    function rowActions(row: AiTemplateRow): RowAction[] {
        if (!mayManage) {
            return [];
        }

        return [
            {
                id: 'edit',
                label: 'Edit',
                icon: <Pencil className="size-4 opacity-70" aria-hidden="true" />,
                onSelect: () => openEdit(row),
            },
            {
                id: 'delete',
                label: 'Delete',
                destructive: true,
                separatorBefore: true,
                icon: <Trash2 className="size-4" aria-hidden="true" />,
                onSelect: () => void remove(row),
            },
        ];
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'AI', href: routeUrl('ai.index') ?? undefined },
        { label: 'Templates' },
    ];

    return (
        <AppLayout title="Prompt templates" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Prompt templates"
                    description="Reusable prompts with typed inputs, so a good prompt is written once."
                    actions={
                        mayManage ? (
                            <Button type="button" onClick={openCreate}>
                                <Plus className="size-4" aria-hidden="true" />
                                New template
                            </Button>
                        ) : null
                    }
                />

                <DataTable<AiTemplateRow>
                    payload={table}
                    propKey="table"
                    name="templates"
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    rowActions={rowActions}
                    searchPlaceholder="Search templates…"
                    caption="Prompt templates in this workspace"
                    emptyState={
                        filtered ? (
                            <EmptyState
                                icon={SearchX}
                                title="No templates match these filters"
                                description="Clear the visibility filter to see every template."
                                className="border-0"
                            />
                        ) : (
                            <EmptyState
                                icon={FileText}
                                title="No templates yet"
                                description="Save a prompt you keep reusing and turn its variable parts into inputs."
                                className="border-0"
                                action={
                                    mayManage ? (
                                        <Button type="button" size="sm" onClick={openCreate}>
                                            <Plus className="size-4" aria-hidden="true" />
                                            New template
                                        </Button>
                                    ) : null
                                }
                            />
                        )
                    }
                />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editing ? `Edit ${editing.name}` : 'New template'}</DialogTitle>
                        <DialogDescription>
                            Wrap the parts that change in <code className="font-mono text-xs">{'{{braces}}'}</code>, then describe each one
                            below.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} noValidate className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="template-name">Name</Label>
                                <Input
                                    id="template-name"
                                    value={form.data.name}
                                    required
                                    aria-invalid={Boolean(form.errors.name)}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                />
                                {form.errors.name && <p className="text-sm font-medium text-destructive">{form.errors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="template-category">Category</Label>
                                <Input
                                    id="template-category"
                                    value={form.data.category}
                                    placeholder="marketing"
                                    onChange={(event) => form.setData('category', event.target.value)}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="template-description">Description</Label>
                            <Input
                                id="template-description"
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="template-prompt">Prompt</Label>
                            <Textarea
                                id="template-prompt"
                                rows={7}
                                value={form.data.prompt}
                                required
                                className="font-mono text-xs"
                                aria-invalid={Boolean(form.errors.prompt)}
                                onChange={(event) => form.setData('prompt', event.target.value)}
                            />
                            {form.errors.prompt && <p className="text-sm font-medium text-destructive">{form.errors.prompt}</p>}
                        </div>

                        {undeclared.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted/40 p-3">
                                <Wand2 className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <span className="text-sm text-muted-foreground">Undeclared placeholders:</span>
                                {undeclared.map((name) => (
                                    <Button key={name} type="button" variant="outline" size="sm" onClick={() => addVariable(name)}>
                                        <Plus className="size-4" aria-hidden="true" />
                                        {name}
                                    </Button>
                                ))}
                            </div>
                        )}

                        <fieldset className="grid gap-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <legend className="text-sm font-medium">Variables</legend>
                                <Button type="button" variant="outline" size="sm" onClick={() => addVariable()}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    Add variable
                                </Button>
                            </div>

                            {form.data.variables.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No variables declared — the prompt is used verbatim.</p>
                            ) : (
                                <ul className="grid gap-3">
                                    {form.data.variables.map((variable, index) => (
                                        <li
                                            key={index}
                                            className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-[1fr_1fr_auto_auto]"
                                        >
                                            <div className="grid gap-1">
                                                <Label htmlFor={`variable-name-${index}`} className="text-xs">
                                                    Placeholder
                                                </Label>
                                                <Input
                                                    id={`variable-name-${index}`}
                                                    value={variable.name}
                                                    className="font-mono text-xs"
                                                    onChange={(event) => updateVariable(index, { name: event.target.value })}
                                                />
                                            </div>

                                            <div className="grid gap-1">
                                                <Label htmlFor={`variable-label-${index}`} className="text-xs">
                                                    Label
                                                </Label>
                                                <Input
                                                    id={`variable-label-${index}`}
                                                    value={variable.label}
                                                    onChange={(event) => updateVariable(index, { label: event.target.value })}
                                                />
                                            </div>

                                            <div className="grid gap-1">
                                                <Label htmlFor={`variable-type-${index}`} className="text-xs">
                                                    Type
                                                </Label>
                                                <Select
                                                    value={variable.type}
                                                    onValueChange={(value) => updateVariable(index, { type: value as AiVariableType })}
                                                >
                                                    <SelectTrigger id={`variable-type-${index}`}>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {VARIABLE_TYPES.map((type) => (
                                                            <SelectItem key={type} value={type}>
                                                                {type}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="flex items-end justify-between gap-2 sm:flex-col sm:items-center">
                                                <label className="flex items-center gap-2 text-xs">
                                                    <Checkbox
                                                        checked={variable.required}
                                                        onCheckedChange={(checked) => updateVariable(index, { required: checked === true })}
                                                    />
                                                    Required
                                                </label>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Remove variable ${variable.name || index + 1}`}
                                                    onClick={() => removeVariable(index)}
                                                >
                                                    <X className="size-4 text-destructive" aria-hidden="true" />
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </fieldset>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="template-provider">Provider override</Label>
                                <Select
                                    value={form.data.provider === '' ? 'default' : form.data.provider}
                                    onValueChange={(value) => form.setData('provider', value === 'default' ? '' : value)}
                                >
                                    <SelectTrigger id="template-provider">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="default">Workspace default</SelectItem>
                                        {providers.map((provider) => (
                                            <SelectItem key={provider.key} value={provider.key}>
                                                {provider.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="template-model">Model override</Label>
                                <Input
                                    id="template-model"
                                    value={form.data.model}
                                    placeholder="Provider default"
                                    className="font-mono text-xs"
                                    onChange={(event) => form.setData('model', event.target.value)}
                                />
                            </div>
                        </div>

                        <label className="flex items-start gap-3 text-sm">
                            <Checkbox
                                checked={form.data.is_shared}
                                onCheckedChange={(checked) => form.setData('is_shared', checked === true)}
                            />
                            <span>
                                <span className="block font-medium">Share with the workspace</span>
                                <span className="block text-xs text-muted-foreground">Private templates are only visible to you.</span>
                            </span>
                        </label>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner className="size-4" aria-hidden="true" />}
                                {editing ? 'Save changes' : 'Create template'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
