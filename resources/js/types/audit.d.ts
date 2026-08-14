import type { EnumOption } from './users';

/*
|------------------------------------------------------------------------------
| Audit
|------------------------------------------------------------------------------
|
| Mirrors the row transformers declared in
| App\Modules\Audit\Http\Controllers\{ActivityLog,LoginHistory,SecurityLog}Controller.
|
*/

export type JsonRecord = Record<string, unknown>;

export interface ActivityRow {
    id: number | string;
    log_name: string | null;
    description: string;
    event: string | null;
    subject_type: string | null;
    subject_id: number | string | null;
    causer: string | null;
    properties: JsonRecord;
    created_at: string | null;
}

export interface LoginRow {
    id: number;
    user: string | null;
    email: string;
    ip_address: string | null;
    device_type: string | null;
    platform: string | null;
    browser: string | null;
    successful: boolean;
    failure_reason: string | null;
    two_factor_used: boolean;
    logged_in_at: string;
    logged_out_at: string | null;
}

export interface SecurityRow {
    id: number;
    user: string | null;
    event: string;
    event_label: string;
    severity: string;
    severity_color: string;
    description: string;
    context: JsonRecord | null;
    ip_address: string | null;
    created_at: string | null;
}

export type SecurityEventOption = EnumOption;
export type SeverityOption = EnumOption;
