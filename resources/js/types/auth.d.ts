import type { TablePayload } from './index';

/*
|------------------------------------------------------------------------------
| Authentication pages
|------------------------------------------------------------------------------
|
| The mirror of App\Providers\FortifyServiceProvider::registerViews() and of the
| controllers in App\Modules\Auth. Every page below types exactly the props its
| server counterpart renders — nothing is optional that the server always sends.
|
*/

/** A provider key from `saas.auth.socials` that has credentials configured. */
export type SocialProvider = string;

export interface LoginPageProps {
    canResetPassword: boolean;
    canRegister: boolean;
    status: string | null;
    socials: SocialProvider[];
}

export interface RegisterPageProps {
    socials: SocialProvider[];
}

export interface ForgotPasswordPageProps {
    status: string | null;
}

export interface ResetPasswordPageProps {
    email: string;
    token: string;
}

export interface VerifyEmailPageProps {
    status: string | null;
}

/*
|------------------------------------------------------------------------------
| Account security
|------------------------------------------------------------------------------
*/

/** App\Modules\Auth\Http\Controllers\TwoFactorController::show() */
export interface TwoFactorPageProps {
    enabled: boolean;
    confirmed: boolean;
    pending: boolean;
    requiresConfirmation: boolean;
    /** Only present while enrolment is in progress. */
    qrCodeSvg: string | null;
    secret: string | null;
}

/** The JSON payload of `settings.two-factor.recovery-codes`. */
export interface RecoveryCodesResponse {
    codes: string[];
}

/** One row of App\Modules\Auth\Http\Controllers\SessionController::sessions() */
export interface SessionEntry {
    id: string;
    ip_address: string | null;
    device: string | null;
    platform: string | null;
    browser: string | null;
    last_active_at: string;
    last_active_human: string;
    is_current: boolean;
}

export interface SessionsPageProps {
    sessions: SessionEntry[];
    /** False on any session driver other than `database`. */
    supported: boolean;
}

/** One row of App\Modules\Auth\Http\Controllers\LoginHistoryController */
export interface LoginHistoryRow {
    id: number;
    ip_address: string | null;
    device_type: string | null;
    platform: string | null;
    browser: string | null;
    location: string | null;
    successful: boolean;
    failure_reason: string | null;
    two_factor_used: boolean;
    logged_in_at: string;
    logged_in_at_human: string;
    logged_out_at: string | null;
    is_current: boolean;
}

export interface LoginHistoryPageProps {
    table: TablePayload<LoginHistoryRow>;
    retention_days: number;
}

/*
|------------------------------------------------------------------------------
| Invitations
|------------------------------------------------------------------------------
*/

/** App\Modules\Company\Http\Controllers\InvitationAcceptanceController::state() */
export type InvitationState = 'acceptable' | 'accepted' | 'revoked' | 'expired' | 'email_mismatch';

/** App\Modules\Company\Http\Resources\InvitationResource */
export interface InvitationPayload {
    id: number;
    email: string;
    role: string;
    role_label: string;
    status: string;
    status_label: string;
    status_color: string;
    permission_roles: string[];
    invited_by: string | null;
    company: string | null;
    is_expired: boolean;
    is_acceptable: boolean;
    expires_at: string;
    accepted_at: string | null;
    created_at: string | null;
}

export interface InvitationShowPageProps {
    invitation: InvitationPayload;
    state: InvitationState;
    token: string;
}
