import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AiGenerationRow, AiPlaygroundPageProps, AiProviderOption, AiTemplateRow } from '@/types/ai';
import { useForm } from '@inertiajs/react';
import { CircleAlert, Info, Save, ScissorsLineDashed, Send, Sparkles, Square, TriangleAlert } from 'lucide-react';
import { useId, useMemo, useState, type FormEvent } from 'react';
import { CopyButton } from '../api/copy-button';
import { useGenerationStream } from './use-generation-stream';

const STATUS_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    success: 'success',
    destructive: 'destructive',
    warning: 'warning',
    muted: 'secondary',
};

interface SaveTemplateForm {
    name: string;
    prompt: string;
    is_shared: boolean;
}

export default function AiPlayground({ providers, default_provider, templates, credits, defaults, recent }: AiPlaygroundPageProps) {
    const { can } = usePermissions();
    const promptId = useId();
    const systemId = useId();
    const outputId = useId();

    const [providerKey, setProviderKey] = useState(default_provider);
    const [model, setModel] = useState('');
    const [templateId, setTemplateId] = useState('');
    const [variables, setVariables] = useState<Record<string, string>>({});
    const [prompt, setPrompt] = useState('');
    const [system, setSystem] = useState('');
    const [saveOpen, setSaveOpen] = useState(false);

    const stream = useGenerationStream(route('ai.stream'));

    const provider = useMemo<AiProviderOption | undefined>(
        () => providers.find((entry) => entry.key === providerKey),
        [providers, providerKey],
    );

    const template = useMemo<AiTemplateRow | undefined>(
        () => templates.find((entry) => String(entry.id) === templateId),
        [templates, templateId],
    );

    const saveForm = useForm<SaveTemplateForm>({ name: '', prompt: '', is_shared: false });

    const configured = providers.some((entry) => entry.is_configured);
    const outOfCredits = credits.enabled && credits.available <= 0;
    const usedPercent =
        credits.allowance > 0 ? Math.min(100, Math.round(((credits.used + credits.reserved) / credits.allowance) * 100)) : 0;

    const missingVariables = (template?.variables ?? []).filter(
        (variable) => variable.required && (variables[variable.name] ?? '').trim() === '',
    );

    const canSubmit =
        !stream.streaming &&
        configured &&
        !outOfCredits &&
        missingVariables.length === 0 &&
        (template !== undefined || prompt.trim() !== '');

    function selectTemplate(value: string): void {
        setTemplateId(value === 'none' ? '' : value);
        setVariables({});
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        void stream.start({
            prompt: template ? undefined : prompt,
            system: system.trim() === '' ? null : system,
            provider: providerKey,
            model: model === '' ? null : model,
            max_tokens: defaults.max_tokens,
            template_id: template ? template.id : null,
            variables,
        });
    }

    function openSaveDialog(): void {
        saveForm.setData({ name: '', prompt: template?.prompt ?? prompt, is_shared: false });
        setSaveOpen(true);
    }

    function saveTemplate(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        saveForm.post(route('ai.templates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                saveForm.reset();
                setSaveOpen(false);
            },
        });
    }

    const finished = stream.generation;
    const refused = finished?.status === 'refused';
    const truncated = finished?.status === 'truncated';

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'AI' }];

    return (
        <AppLayout title="AI playground" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="AI playground"
                    description="Run a prompt against any configured provider. Output streams as it is generated."
                    actions={
                        can('ai.providers.manage') ? (
                            <Button asChild variant="outline" size="sm">
                                <a href={route('ai.providers.index')}>Configure providers</a>
                            </Button>
                        ) : null
                    }
                />

                {!configured && (
                    <Alert variant="warning">
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>No provider is configured</AlertTitle>
                        <AlertDescription>
                            {can('ai.providers.manage')
                                ? 'Add an API key under AI → Providers before running a generation.'
                                : 'Ask an administrator to add a provider API key for this workspace.'}
                        </AlertDescription>
                    </Alert>
                )}

                {outOfCredits && (
                    <Alert variant="destructive">
                        <CircleAlert aria-hidden="true" />
                        <AlertTitle>Out of AI credits</AlertTitle>
                        <AlertDescription>
                            This workspace has used its {credits.allowance} credits for {credits.period}. An administrator can raise the
                            allowance under AI → Credits.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Prompt</CardTitle>
                                <CardDescription>Pick a provider and model, or start from a saved template.</CardDescription>
                            </CardHeader>

                            <CardContent>
                                <form onSubmit={submit} noValidate className="space-y-5">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="ai-provider">Provider</Label>
                                            <Select
                                                value={providerKey}
                                                onValueChange={(value) => {
                                                    setProviderKey(value);
                                                    setModel('');
                                                }}
                                            >
                                                <SelectTrigger id="ai-provider">
                                                    <SelectValue placeholder="Choose a provider" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {providers.map((entry) => (
                                                        <SelectItem key={entry.key} value={entry.key} disabled={!entry.is_configured}>
                                                            {entry.label}
                                                            {entry.is_configured ? '' : ' — no key'}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="ai-model">Model</Label>
                                            <Select
                                                value={model === '' ? 'default' : model}
                                                onValueChange={(value) => setModel(value === 'default' ? '' : value)}
                                            >
                                                <SelectTrigger id="ai-model">
                                                    <SelectValue placeholder="Provider default" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="default">Provider default</SelectItem>
                                                    {(provider?.models ?? []).map((entry) => (
                                                        <SelectItem key={entry.id} value={entry.id}>
                                                            {entry.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="ai-template">Template</Label>
                                        <Select value={templateId === '' ? 'none' : templateId} onValueChange={selectTemplate}>
                                            <SelectTrigger id="ai-template">
                                                <SelectValue placeholder="No template" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">No template — free-form prompt</SelectItem>
                                                {templates.map((entry) => (
                                                    <SelectItem key={entry.id} value={String(entry.id)}>
                                                        {entry.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {template ? (
                                        <fieldset className="grid gap-4">
                                            <legend className="text-sm font-medium">Template variables</legend>

                                            <p className="rounded-md border border-border bg-muted/40 p-3 font-mono text-xs break-words">
                                                {template.prompt}
                                            </p>

                                            {template.variables.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">This template takes no variables.</p>
                                            ) : (
                                                template.variables.map((variable) => (
                                                    <div key={variable.name} className="grid gap-2">
                                                        <Label htmlFor={`variable-${variable.name}`}>
                                                            {variable.label}
                                                            {variable.required && (
                                                                <span aria-hidden="true" className="text-destructive">
                                                                    {' '}
                                                                    *
                                                                </span>
                                                            )}
                                                        </Label>

                                                        {variable.type === 'textarea' ? (
                                                            <Textarea
                                                                id={`variable-${variable.name}`}
                                                                rows={3}
                                                                required={variable.required}
                                                                value={variables[variable.name] ?? ''}
                                                                onChange={(event) =>
                                                                    setVariables((current) => ({
                                                                        ...current,
                                                                        [variable.name]: event.target.value,
                                                                    }))
                                                                }
                                                            />
                                                        ) : (
                                                            <Input
                                                                id={`variable-${variable.name}`}
                                                                type={variable.type === 'number' ? 'number' : 'text'}
                                                                required={variable.required}
                                                                value={variables[variable.name] ?? ''}
                                                                onChange={(event) =>
                                                                    setVariables((current) => ({
                                                                        ...current,
                                                                        [variable.name]: event.target.value,
                                                                    }))
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                ))
                                            )}
                                        </fieldset>
                                    ) : (
                                        <div className="grid gap-2">
                                            <Label htmlFor={promptId}>Prompt</Label>
                                            <Textarea
                                                id={promptId}
                                                rows={8}
                                                value={prompt}
                                                placeholder="Summarise the attached release notes for a non-technical audience."
                                                onChange={(event) => setPrompt(event.target.value)}
                                            />
                                        </div>
                                    )}

                                    <div className="grid gap-2">
                                        <Label htmlFor={systemId}>System instructions (optional)</Label>
                                        <Textarea
                                            id={systemId}
                                            rows={2}
                                            value={system}
                                            placeholder="You are a concise technical writer."
                                            onChange={(event) => setSystem(event.target.value)}
                                        />
                                    </div>

                                    {missingVariables.length > 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Fill in {missingVariables.map((variable) => variable.label).join(', ')} to continue.
                                        </p>
                                    )}

                                    <div className="flex flex-wrap gap-2">
                                        <Button type="submit" disabled={!canSubmit}>
                                            {stream.streaming ? (
                                                <Spinner className="size-4" aria-hidden="true" />
                                            ) : (
                                                <Send className="size-4" aria-hidden="true" />
                                            )}
                                            Generate
                                        </Button>

                                        {stream.streaming && (
                                            <Button type="button" variant="destructive" onClick={stream.stop}>
                                                <Square className="size-4" aria-hidden="true" />
                                                Stop
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <CardTitle>Output</CardTitle>
                                        <CardDescription aria-live="polite">
                                            {stream.streaming ? 'Generating…' : finished ? finished.status_label : 'Nothing generated yet.'}
                                        </CardDescription>
                                    </div>

                                    <div className="flex shrink-0 flex-wrap gap-2">
                                        <CopyButton value={stream.output} label="Copy output" disabled={stream.output === ''} />
                                        {can('ai.templates.manage') && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={prompt.trim() === '' && !template}
                                                onClick={openSaveDialog}
                                            >
                                                <Save className="size-4" aria-hidden="true" />
                                                Save as template
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent className="space-y-4">
                                {stream.error && (
                                    <Alert variant="destructive">
                                        <CircleAlert aria-hidden="true" />
                                        <AlertTitle>Generation failed</AlertTitle>
                                        <AlertDescription className="break-words">{stream.error}</AlertDescription>
                                    </Alert>
                                )}

                                {refused && (
                                    <Alert variant="warning">
                                        <TriangleAlert aria-hidden="true" />
                                        <AlertTitle>The model declined this request</AlertTitle>
                                        <AlertDescription>
                                            A refusal is a normal outcome, not an error. Rephrase the prompt or try a different model. No
                                            credits were charged.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {truncated && (
                                    <Alert variant="info">
                                        <ScissorsLineDashed aria-hidden="true" />
                                        <AlertTitle>Output was cut short</AlertTitle>
                                        <AlertDescription>
                                            The response hit the {defaults.max_tokens}-token ceiling. What you see below is complete up to
                                            that point.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div
                                    id={outputId}
                                    role="log"
                                    aria-live="polite"
                                    aria-busy={stream.streaming}
                                    className="min-h-40 overflow-x-auto rounded-md border border-border bg-muted/40 p-3"
                                >
                                    {stream.output === '' ? (
                                        <p className="text-sm text-muted-foreground">
                                            {stream.streaming ? 'Waiting for the first token…' : 'Run a prompt to see the response here.'}
                                        </p>
                                    ) : (
                                        <p className="text-sm whitespace-pre-wrap">{stream.output}</p>
                                    )}
                                </div>

                                {finished && (
                                    <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                        <div>
                                            <dt className="text-muted-foreground">Prompt tokens</dt>
                                            <dd className="tabular-nums">{finished.prompt_tokens}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">Output tokens</dt>
                                            <dd className="tabular-nums">{finished.completion_tokens}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">Credits</dt>
                                            <dd className="tabular-nums">{finished.credits_charged}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">Duration</dt>
                                            <dd className="tabular-nums">{finished.duration_ms} ms</dd>
                                        </div>
                                    </dl>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Credits</CardTitle>
                                <CardDescription>{credits.period}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {credits.enabled ? (
                                    <>
                                        <Progress value={usedPercent} aria-label={`${credits.used} of ${credits.allowance} credits used`} />
                                        <dl className="grid grid-cols-3 gap-2 text-sm">
                                            <div>
                                                <dt className="text-muted-foreground">Used</dt>
                                                <dd className="tabular-nums">{credits.used}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">Held</dt>
                                                <dd className="tabular-nums">{credits.reserved}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">Left</dt>
                                                <dd className="tabular-nums">{credits.available}</dd>
                                            </div>
                                        </dl>
                                    </>
                                ) : (
                                    <Alert>
                                        <Info aria-hidden="true" />
                                        <AlertDescription>Credit metering is disabled on this installation.</AlertDescription>
                                    </Alert>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Recent generations</CardTitle>
                                <CardDescription>The last five runs in this workspace.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {recent.length === 0 ? (
                                    <EmptyState
                                        icon={Sparkles}
                                        title="Nothing yet"
                                        description="Your generations will be listed here."
                                        className="border-0"
                                    />
                                ) : (
                                    <ul className="space-y-3">
                                        {recent.map((entry: AiGenerationRow) => (
                                            <li key={entry.id} className="min-w-0 space-y-1 border-b border-border pb-3 last:border-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant={STATUS_VARIANT[entry.status_color] ?? 'secondary'}>
                                                        {entry.status_label}
                                                    </Badge>
                                                    <span className="font-mono text-xs text-muted-foreground">{entry.model}</span>
                                                </div>
                                                <p className="line-clamp-2 text-sm break-words">{entry.input}</p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Dialog open={saveOpen} onOpenChange={setSaveOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Save as template</DialogTitle>
                        <DialogDescription>
                            Wrap the parts that change in <code className="font-mono text-xs">{'{{braces}}'}</code> to turn them into
                            inputs.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={saveTemplate} noValidate className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="template-name">Name</Label>
                            <Input
                                id="template-name"
                                value={saveForm.data.name}
                                required
                                aria-invalid={Boolean(saveForm.errors.name)}
                                onChange={(event) => saveForm.setData('name', event.target.value)}
                            />
                            {saveForm.errors.name && <p className="text-sm font-medium text-destructive">{saveForm.errors.name}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="template-prompt">Prompt</Label>
                            <Textarea
                                id="template-prompt"
                                rows={6}
                                value={saveForm.data.prompt}
                                required
                                onChange={(event) => saveForm.setData('prompt', event.target.value)}
                            />
                            {saveForm.errors.prompt && <p className="text-sm font-medium text-destructive">{saveForm.errors.prompt}</p>}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setSaveOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saveForm.processing}>
                                {saveForm.processing && <Spinner className="size-4" aria-hidden="true" />}
                                Save template
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
