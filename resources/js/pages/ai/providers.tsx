import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedProps } from '@/types';
import type { AiProvidersPageProps } from '@/types/ai';
import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, Info, PlugZap, Save, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { SecretField } from '@/components/forms/secret-field';

interface TestResult {
    ok: boolean;
    message: string;
    model?: string;
    tokens?: number;
}

export default function AiProviders({ providers, mask, default_provider, allow_tenant_keys = true }: AiProvidersPageProps) {
    const { errors } = usePage<SharedProps>().props;

    const [draft, setDraft] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState<string | null>(null);
    const [results, setResults] = useState<Record<string, TestResult>>({});

    const dirty = allow_tenant_keys && Object.values(draft).some((value) => value !== '');

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!allow_tenant_keys) {
            return;
        }

        setSaving(true);

        router.patch(
            route('ai.providers.update'),
            // A blank field is simply not sent: the server treats an absent or
            // masked value as "leave the stored credential alone", never as a
            // clear. Sending '' would be indistinguishable from a deletion.
            { keys: draft },
            {
                preserveScroll: true,
                onSuccess: () => setDraft({}),
                onFinish: () => setSaving(false),
            },
        );
    }

    async function test(providerKey: string): Promise<void> {
        setTesting(providerKey);

        try {
            const response = await window.axios.post<TestResult>(route('ai.providers.test', providerKey));

            setResults((current) => ({ ...current, [providerKey]: response.data }));
            toast.success(response.data.message);
        } catch {
            const failure: TestResult = { ok: false, message: 'The provider rejected the request. Check the stored key.' };

            setResults((current) => ({ ...current, [providerKey]: failure }));
            toast.error(failure.message);
        } finally {
            setTesting(null);
        }
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'AI', href: routeUrl('ai.index') ?? undefined },
        { label: 'Providers' },
    ];

    return (
        <AppLayout title="AI providers" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="AI providers"
                    description="Per-workspace credentials. Keys are encrypted at rest and never sent back to the browser."
                />

                <Alert>
                    <Info aria-hidden="true" />
                    <AlertDescription>
                        {allow_tenant_keys
                            ? 'A blank field leaves the stored key untouched. Saving a new value replaces it immediately for everyone in this workspace. When no workspace key is set, the platform credential is used.'
                            : 'Your administrator manages provider credentials for every tenant. Workspace-level API keys are disabled.'}
                    </AlertDescription>
                </Alert>

                <form onSubmit={submit} noValidate className="space-y-4">
                    {providers.map((provider) => {
                        const result = results[provider.key];

                        return (
                            <Card key={provider.key}>
                                <CardHeader>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0">
                                            <CardTitle className="flex flex-wrap items-center gap-2">
                                                {provider.label}
                                                {provider.key === default_provider && <Badge variant="secondary">Default</Badge>}
                                            </CardTitle>
                                            <CardDescription>
                                                {provider.models.length} model{provider.models.length === 1 ? '' : 's'} available
                                            </CardDescription>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="shrink-0"
                                            disabled={!provider.is_configured || testing === provider.key}
                                            onClick={() => void test(provider.key)}
                                        >
                                            {testing === provider.key ? (
                                                <Spinner className="size-4" aria-hidden="true" />
                                            ) : (
                                                <PlugZap className="size-4" aria-hidden="true" />
                                            )}
                                            Test connection
                                        </Button>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-3">
                                    {allow_tenant_keys ? (
                                        <SecretField
                                            label="API key"
                                            value={draft[provider.key] ?? ''}
                                            isSet={provider.is_configured}
                                            mask={mask}
                                            error={errors[`keys.${provider.key}`]}
                                            onChange={(value) => setDraft((current) => ({ ...current, [provider.key]: value }))}
                                        />
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            {provider.is_configured
                                                ? 'Configured by the platform operator.'
                                                : 'Not configured yet. Ask your platform administrator to add a key.'}
                                        </p>
                                    )}

                                    <div className="flex flex-wrap gap-1">
                                        {provider.models.map((model) => (
                                            <Badge key={model.id} variant="outline" className="font-mono">
                                                {model.id}
                                            </Badge>
                                        ))}
                                    </div>

                                    {result && (
                                        <Alert variant={result.ok ? 'success' : 'destructive'}>
                                            {result.ok ? <CheckCircle2 aria-hidden="true" /> : <XCircle aria-hidden="true" />}
                                            <AlertDescription>
                                                {result.message}
                                                {result.ok && result.model && (
                                                    <span className="block font-mono text-xs">
                                                        {result.model} · {result.tokens ?? 0} tokens
                                                    </span>
                                                )}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}

                    {allow_tenant_keys && (
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="submit" disabled={!dirty || saving}>
                                {saving ? <Spinner className="size-4" aria-hidden="true" /> : <Save className="size-4" aria-hidden="true" />}
                                Save credentials
                            </Button>

                            {!dirty && <p className="text-sm text-muted-foreground">Enter a key to enable saving.</p>}
                        </div>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}
