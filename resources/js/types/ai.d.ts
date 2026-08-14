import type { TablePayload } from './index';

/*
|------------------------------------------------------------------------------
| AI
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\AI\Http\Resources\* and the props the AI controllers
| attach alongside them.
|
*/

export interface AiModelOption {
    id: string;
    label: string;
}

export interface AiProviderOption {
    key: string;
    label: string;
    models: AiModelOption[];
    /** Whether a credential is stored. The credential itself never arrives. */
    is_configured: boolean;
    is_default: boolean;
}

export type AiVariableType = 'text' | 'textarea' | 'number' | 'select';

export interface AiTemplateVariable {
    name: string;
    label: string;
    type: AiVariableType;
    required: boolean;
}

export interface AiTemplateRow {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    category: string | null;
    prompt: string;
    variables: AiTemplateVariable[];
    /** `{{variable}}` names the prompt body actually references. */
    placeholders: string[];
    provider: string | null;
    model: string | null;
    is_shared: boolean;
    usage_count: number;
    author: string | null;
    created_at: string | null;
}

export type AiGenerationStatus = 'pending' | 'streaming' | 'completed' | 'refused' | 'truncated' | 'failed';

export interface AiGenerationRow {
    id: number;
    provider: string;
    model: string;
    input: string;
    output: string | null;
    prompt_tokens: number;
    completion_tokens: number;
    total_tokens: number;
    credits_charged: number;
    duration_ms: number;
    status: AiGenerationStatus;
    status_label: string;
    status_color: string;
    stop_reason: string | null;
    error: string | null;
    template_id: number | null;
    template: string | null;
    user: string | null;
    created_at: string | null;
}

export interface AiCreditSummary {
    enabled: boolean;
    period: string;
    allowance: number;
    used: number;
    reserved: number;
    available: number;
}

export interface AiCreditTransactionRow {
    id: number;
    period: string;
    type: string;
    type_label: string;
    type_color: string;
    credits: number;
    description: string | null;
    generation_id: number | null;
    user: string | null;
    created_at: string | null;
}

export interface AiPlaygroundPageProps {
    providers: AiProviderOption[];
    default_provider: string;
    templates: AiTemplateRow[];
    credits: AiCreditSummary;
    defaults: { max_tokens: number };
    recent: AiGenerationRow[];
}

export interface AiTemplatesPageProps {
    table: TablePayload<AiTemplateRow>;
    providers: AiProviderOption[];
    can: { manage: boolean };
}

export interface AiHistoryPageProps {
    table: TablePayload<AiGenerationRow>;
}

export interface AiCreditsPageProps {
    balance: AiCreditSummary;
    rates: { per_1k_input: number; per_1k_output: number };
    ledger: TablePayload<AiCreditTransactionRow>;
    can: { manage: boolean };
}

export interface AiProvidersPageProps {
    providers: AiProviderOption[];
    /** The placeholder the server sends in place of a stored credential. */
    mask: string;
    default_provider: string;
    /** When false, tenants cannot store BYOK credentials. */
    allow_tenant_keys: boolean;
}
