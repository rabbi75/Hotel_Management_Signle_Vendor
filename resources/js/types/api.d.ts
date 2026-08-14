import type { TablePayload } from './index';

/*
|------------------------------------------------------------------------------
| Developer area
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\Api\Http\Resources\* and the props each controller in
| App\Modules\Api\Http\Controllers attaches alongside them.
|
*/

export interface ApiTokenRow {
    id: number;
    name: string;
    /** A prefix of the stored hash — the plaintext is never recoverable. */
    fingerprint: string;
    abilities: string[];
    last_used_at: string | null;
    expires_at: string | null;
    is_expired: boolean;
    created_at: string | null;
}

/** Flashed once, on creation. Never present on any other response. */
export interface CreatedToken {
    name: string;
    plain_text: string;
}

export interface ApiTokensPageProps {
    table: TablePayload<ApiTokenRow>;
    /** Ability key => human description. */
    abilities: Record<string, string>;
    default_expiry_days: number;
    can: { create: boolean; revoke: boolean };
}

export interface WebhookEndpointRow {
    id: number;
    url: string;
    description: string | null;
    events: string[];
    /** A placeholder; the signing secret itself is shown once, on creation. */
    secret_hint: string;
    is_active: boolean;
    failure_count: number;
    last_success_at: string | null;
    last_failure_at: string | null;
    disabled_at: string | null;
    created_at: string | null;
}

export interface WebhookDeliveryRow {
    id: number;
    endpoint_id: number;
    endpoint_url: string | null;
    event: string;
    payload: Record<string, unknown>;
    attempt: number;
    status: string;
    status_label: string;
    status_color: string;
    status_code: number | null;
    response_body: string | null;
    error: string | null;
    duration_ms: number | null;
    delivered_at: string | null;
    next_retry_at: string | null;
    created_at: string | null;
}

export interface WebhookEventDefinition {
    name: string;
    group: string;
    description: string;
}

export interface CreatedWebhookSecret {
    endpoint_id: number;
    secret: string;
}

export interface WebhooksPageProps {
    endpoints: WebhookEndpointRow[];
    deliveries: TablePayload<WebhookDeliveryRow>;
    events: WebhookEventDefinition[];
    signature_header: string;
    timestamp_header: string;
    can: { manage: boolean };
}

export interface ApiRequestLogRow {
    id: number;
    method: string;
    path: string;
    route_name: string | null;
    status: number;
    is_error: boolean;
    duration_ms: number;
    ip_address: string | null;
    user_agent: string | null;
    user: string | null;
    token: string | null;
    api_token_id: number | null;
    request_body: Record<string, unknown> | null;
    response_body: Record<string, unknown> | null;
    created_at: string | null;
}

export interface ApiLogsPageProps {
    table: TablePayload<ApiRequestLogRow>;
    retention_days: number;
    bodies_logged: boolean;
}

/*
|------------------------------------------------------------------------------
| OpenAPI
|------------------------------------------------------------------------------
|
| A deliberately narrow view of the document: only the parts the docs page
| renders are typed, and everything else stays `unknown`.
|
*/

export interface OpenApiParameter {
    name: string;
    in: string;
    required?: boolean;
    description?: string;
    schema?: Record<string, unknown>;
}

export interface OpenApiOperation {
    operationId?: string;
    tags?: string[];
    summary?: string;
    parameters?: OpenApiParameter[];
    requestBody?: Record<string, unknown>;
    responses?: Record<string, { description?: string }>;
}

export interface OpenApiSpec {
    openapi: string;
    info: { title: string; version: string; description?: string };
    servers?: { url: string }[];
    components?: {
        securitySchemes?: Record<string, { type?: string; scheme?: string; description?: string }>;
        schemas?: Record<string, Record<string, unknown>>;
    };
    paths: Record<string, Record<string, OpenApiOperation>>;
}

export interface ApiDocsPageProps {
    spec: OpenApiSpec;
    spec_url: string;
}
