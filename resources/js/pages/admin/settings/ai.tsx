import { FormActions } from '@/components/forms/form-actions';
import { SecretField } from '@/components/forms/secret-field';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { toast } from '@/components/ui/toast';
import { AdminSettingsLayout } from '@/layouts/admin-settings-layout';
import type { SharedProps } from '@/types';
import type { AiProviderOption } from '@/types/ai';
import type { AiSettingsPageProps } from '@/types/settings';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, Info, PlugZap, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { untouchedSecret, useSettingsSubmit, type SettingsPayload } from './use-settings-form';

interface TestResult {
    ok: boolean;
    message: string;
    model?: string;
    tokens?: number;
}

const PROVIDER_FIELDS: Record<string, { field: string; label: string; description: string }> = {
    openai: {
        field: 'openai_api_key',
        label: 'OpenAI',
        description: 'GPT models for assistive generation across tenants.',
    },
    anthropic: {
        field: 'anthropic_api_key',
        label: 'Anthropic',
        description: 'Claude models for higher-quality reasoning workloads.',
    },
    deepseek: {
        field: 'deepseek_api_key',
        label: 'DeepSeek',
        description: 'Cost-efficient OpenAI-compatible chat models.',
    },
    gemini: {
        field: 'gemini_api_key',
        label: 'Google Gemini',
        description: 'Gemini Flash / Pro for fast multimodal assistants.',
    },
    grok: {
        field: 'grok_api_key',
        label: 'xAI Grok',
        description: 'Grok models via the xAI API.',
    },
};

const HANDLED = [
    'enabled',
    'default_provider',
    'credits_enabled',
    'monthly_credits',
    'allow_tenant_keys',
    ...Object.values(PROVIDER_FIELDS).map((entry) => entry.field),
];

export default function AiSettingsPage({ settings, secrets, providers, mask }: AiSettingsPageProps) {
    const { errors } = usePage<SharedProps>().props;
    const { submit, submitting, saved } = useSettingsSubmit(route('admin.settings.ai.update'));

    const [enabled, setEnabled] = useState(settings.enabled);
    const [creditsEnabled, setCreditsEnabled] = useState(settings.credits_enabled);
    const [allowTenantKeys, setAllowTenantKeys] = useState(settings.allow_tenant_keys);
    const [defaultProvider, setDefaultProvider] = useState(settings.default_provider);
    const [monthlyCredits, setMonthlyCredits] = useState(String(settings.monthly_credits));
    const [draft, setDraft] = useState<Record<string, string>>(() =>
        Object.fromEntries(Object.values(PROVIDER_FIELDS).map((entry) => [entry.field, ''])),
    );
    const [testing, setTesting] = useState<string | null>(null);
    const [results, setResults] = useState<Record<string, TestResult>>({});

    const dirty =
        enabled !== settings.enabled ||
        creditsEnabled !== settings.credits_enabled ||
        allowTenantKeys !== settings.allow_tenant_keys ||
        defaultProvider !== settings.default_provider ||
        monthlyCredits !== String(settings.monthly_credits) ||
        Object.values(draft).some((value) => value !== '');

    function onSubmit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const payload: SettingsPayload = {
            enabled,
            credits_enabled: creditsEnabled,
            allow_tenant_keys: allowTenantKeys,
            default_provider: defaultProvider,
            monthly_credits: Number(monthlyCredits) || 0,
        };

        for (const [field, value] of Object.entries(draft)) {
            payload[field] = untouchedSecret(value);
        }

        submit(payload, () =>
            setDraft(Object.fromEntries(Object.values(PROVIDER_FIELDS).map((entry) => [entry.field, '']))),
        );
    }

    async function test(providerKey: string): Promise<void> {
        setTesting(providerKey);

        try {
            const response = await window.axios.post<TestResult>(route('admin.settings.ai.test', providerKey));

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

    const catalogue = providers.length > 0 ? providers : (Object.keys(PROVIDER_FIELDS) as string[]).map(
        (key): AiProviderOption => ({
            key,
            label: PROVIDER_FIELDS[key]?.label ?? key,
            models: [],
            is_configured: secrets[PROVIDER_FIELDS[key]?.field ?? ''] === true,
            is_default: key === defaultProvider,
        }),
    );

    return (
        <AdminSettingsLayout
            title="AI"
            description="Platform LLM credentials and defaults used when a tenant has not brought their own key."
        >
            <ErrorSummary errors={errors} handled={HANDLED} />

            <Alert className="mb-6">
                <Info aria-hidden="true" />
                <AlertDescription>
                    Configure OpenAI, Anthropic, DeepSeek, Gemini, and Grok here. Tenants inherit these keys for
                    generation, credits, and assistive features unless they store their own (when BYOK is allowed).
                </AlertDescription>
            </Alert>

            <form onSubmit={onSubmit} noValidate className="space-y-6">
                <PanelCard title="Platform policy" description="Controls AI availability and credit provisioning for every tenant.">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="flex items-center justify-between gap-4 rounded-md border px-3 py-2 sm:col-span-2">
                            <div>
                                <Label htmlFor="ai-enabled">Enable AI features</Label>
                                <p className="text-xs text-muted-foreground">Master switch for platform-assisted generation.</p>
                            </div>
                            <Switch id="ai-enabled" checked={enabled} onCheckedChange={setEnabled} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="ai-default-provider">Default provider</Label>
                            <Select value={defaultProvider} onValueChange={setDefaultProvider}>
                                <SelectTrigger id="ai-default-provider">
                                    <SelectValue placeholder="Choose a provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    {catalogue.map((provider) => (
                                        <SelectItem key={provider.key} value={provider.key}>
                                            {provider.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.default_provider && <p className="text-sm text-destructive">{errors.default_provider}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="ai-monthly-credits">Monthly credit allowance</Label>
                            <Input
                                id="ai-monthly-credits"
                                type="number"
                                min={0}
                                value={monthlyCredits}
                                onChange={(event) => setMonthlyCredits(event.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">Used when a plan does not set an AI credit limit.</p>
                            {errors.monthly_credits && <p className="text-sm text-destructive">{errors.monthly_credits}</p>}
                        </div>

                        <div className="flex items-center justify-between gap-4 rounded-md border px-3 py-2">
                            <div>
                                <Label htmlFor="ai-credits-enabled">Enforce credits</Label>
                                <p className="text-xs text-muted-foreground">Charge monthly allowances per tenant.</p>
                            </div>
                            <Switch id="ai-credits-enabled" checked={creditsEnabled} onCheckedChange={setCreditsEnabled} />
                        </div>

                        <div className="flex items-center justify-between gap-4 rounded-md border px-3 py-2">
                            <div>
                                <Label htmlFor="ai-allow-tenant-keys">Allow tenant API keys</Label>
                                <p className="text-xs text-muted-foreground">Let clients bring their own provider credentials.</p>
                            </div>
                            <Switch id="ai-allow-tenant-keys" checked={allowTenantKeys} onCheckedChange={setAllowTenantKeys} />
                        </div>
                    </div>
                </PanelCard>

                {catalogue.map((provider) => {
                    const meta = PROVIDER_FIELDS[provider.key];

                    if (!meta) {
                        return null;
                    }

                    const result = results[provider.key];

                    return (
                        <PanelCard key={provider.key} title={meta.label} description={meta.description}>
                            <div className="space-y-4">
                                <SecretField
                                    label="API key"
                                    value={draft[meta.field] ?? ''}
                                    isSet={secrets[meta.field] === true}
                                    mask={mask}
                                    error={errors[meta.field]}
                                    onChange={(value) => setDraft((current) => ({ ...current, [meta.field]: value }))}
                                />

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={testing === provider.key || secrets[meta.field] !== true}
                                        onClick={() => void test(provider.key)}
                                    >
                                        {testing === provider.key ? <Spinner className="size-3.5" /> : <PlugZap className="size-3.5" />}
                                        Test connection
                                    </Button>

                                    {result && (
                                        <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                            {result.ok ? (
                                                <CheckCircle2 className="size-4 text-emerald-600" aria-hidden="true" />
                                            ) : (
                                                <XCircle className="size-4 text-destructive" aria-hidden="true" />
                                            )}
                                            {result.message}
                                            {result.model ? ` · ${result.model}` : ''}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </PanelCard>
                    );
                })}

                <FormActions
                    dirty={dirty}
                    submitting={submitting}
                    saved={saved}
                    submitLabel="Save AI settings"
                    cancelLabel="Reset"
                    onCancel={() => {
                        setEnabled(settings.enabled);
                        setCreditsEnabled(settings.credits_enabled);
                        setAllowTenantKeys(settings.allow_tenant_keys);
                        setDefaultProvider(settings.default_provider);
                        setMonthlyCredits(String(settings.monthly_credits));
                        setDraft(Object.fromEntries(Object.values(PROVIDER_FIELDS).map((entry) => [entry.field, ''])));
                    }}
                />
            </form>
        </AdminSettingsLayout>
    );
}
