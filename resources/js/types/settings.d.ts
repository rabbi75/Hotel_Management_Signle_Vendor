import type { Appearance, LocaleDefinition } from './index';
import type { AiProviderOption } from './ai';

/*
|------------------------------------------------------------------------------
| Settings panels
|------------------------------------------------------------------------------
|
| The mirror of App\Modules\Settings\Http\Controllers\SettingsController::panel()
| and of App\Modules\Settings\Support\SettingsSchema. Field names are the setting
| key with its group prefix removed, exactly as the form requests expect them.
|
*/

/** A `value`/`label`/`color` triple produced by App\Support\Enums\Concerns\HasLabel. */
export interface EnumOption {
    value: string;
    label: string;
    color: string;
}

/** Which encrypted fields currently hold a value. Keyed by form field name. */
export type SecretFlags = Record<string, boolean>;

interface PanelProps<TValues> {
    group: string;
    settings: TValues;
    secrets: SecretFlags;
    tabs: string[];
}

export interface GeneralSettings {
    app_name: string;
    short_name: string;
    tagline: string | null;
    support_email: string;
    contact_phone: string | null;
    address: string | null;
    registration_enabled: boolean;
}

export type GeneralSettingsPageProps = PanelProps<GeneralSettings>;

export interface LocalizationSettings {
    default_locale: string;
    default_timezone: string;
    default_currency: string;
    date_format: string;
    time_format: string;
    week_starts_on: number;
    enabled_locales: string[];
}

export interface LocalizationSettingsPageProps extends PanelProps<LocalizationSettings> {
    locales: Record<string, LocaleDefinition>;
    timezones: string[];
}

export type MailTransport = 'smtp' | 'ses' | 'postmark' | 'resend' | 'sendmail' | 'log' | 'array';

export interface MailSettings {
    mailer: MailTransport;
    host: string | null;
    port: number | null;
    username: string | null;
    /** Masked; `null` when never configured. */
    password: string | null;
    encryption: 'tls' | 'ssl' | 'none' | null;
    from_address: string;
    from_name: string;
}

export interface MailSettingsPageProps extends PanelProps<MailSettings> {
    transports: MailTransport[];
}

export type StorageDriver = 'local' | 'public' | 's3' | 'r2';

export interface StorageSettings {
    disk: StorageDriver;
    max_upload_kb: number;
    s3_key: string | null;
    s3_secret: string | null;
    s3_region: string | null;
    s3_bucket: string | null;
    s3_endpoint: string | null;
    s3_use_path_style: boolean;
    r2_account_id: string | null;
    r2_access_key_id: string | null;
    r2_secret_access_key: string | null;
    r2_bucket: string | null;
    r2_public_url: string | null;
}

export interface StorageSettingsPageProps extends PanelProps<StorageSettings> {
    drivers: { value: StorageDriver; label: string }[];
}

/** Every value here is encrypted at rest, so each arrives masked or `null`. */
export interface ApiKeySettings {
    pusher_app_id: string | null;
    pusher_key: string | null;
    pusher_secret: string | null;
    pusher_cluster: string | null;
    google_client_id: string | null;
    google_client_secret: string | null;
    google_maps_key: string | null;
    facebook_app_id: string | null;
    facebook_app_secret: string | null;
    openai_api_key: string | null;
    openai_organization: string | null;
}

export interface ApiKeySettingsPageProps extends PanelProps<ApiKeySettings> {
    providers: string[];
    /** The placeholder a stored secret is replaced by; posting it back is a no-op. */
    mask: string;
}

export interface AiSettings {
    enabled: boolean;
    default_provider: string;
    credits_enabled: boolean;
    monthly_credits: number;
    allow_tenant_keys: boolean;
    anthropic_api_key: string | null;
    openai_api_key: string | null;
    gemini_api_key: string | null;
    deepseek_api_key: string | null;
    grok_api_key: string | null;
}

export interface AiSettingsPageProps extends PanelProps<AiSettings> {
    providers: AiProviderOption[];
    mask: string;
}

export interface AppearanceSettings {
    theme: Appearance;
    primary_color: string;
    sidebar_variant: 'sidebar' | 'floating' | 'inset';
    logo_url: string | null;
    dark_logo_url: string | null;
    icon_url: string | null;
    favicon_url: string | null;
    landing_logo_url: string | null;
    custom_css: string | null;
}

export interface AppearanceSettingsPageProps extends PanelProps<AppearanceSettings> {
    themes: EnumOption[];
}

export interface SecuritySettings {
    email_verification_required: boolean;
    two_factor_enforced: boolean;
    password_min_length: number;
    password_requires_symbols: boolean;
    password_expires_days: number | null;
    max_login_attempts: number;
    session_lifetime_minutes: number;
    force_https: boolean;
    allowed_ips: string[];
}

export type SecuritySettingsPageProps = PanelProps<SecuritySettings>;

export interface MaintenanceSettings {
    enabled: boolean;
    message: string | null;
    retry_after: number | null;
    /** Masked; `null` when no bypass token has been stored. */
    secret: string | null;
    allowed_ips: string[];
}

export interface MaintenanceSettingsPageProps extends PanelProps<MaintenanceSettings> {
    /**
     * The bypass token, in plaintext, on the one response that follows
     * generating it. `null` on every other visit — it is never readable again.
     */
    generatedSecret: string | null;
}

/*
|------------------------------------------------------------------------------
| Password
|------------------------------------------------------------------------------
|
| The mirror of App\Modules\Settings\Http\Controllers\PasswordSettingsController.
| The form itself submits to Fortify's `user-password.update` endpoint.
|
*/

export interface PasswordSettingsPageProps {
    /** ISO-8601, or `null` when the password has never been changed. */
    passwordChangedAt: string | null;
}

/*
|------------------------------------------------------------------------------
| Profile
|------------------------------------------------------------------------------
|
| The mirror of App\Modules\User\Http\Controllers\ProfileController::show().
|
*/

/** The subset of App\Modules\User\Http\Resources\UserResource the profile page reads. */
export interface ProfileUser {
    id: number;
    uuid: string;
    name: string;
    first_name: string | null;
    last_name: string | null;
    initials: string;
    email: string;
    phone: string | null;
    job_title: string | null;
    bio: string | null;
    avatar_url: string | null;
    status: { value: string; label: string; color: string };
    timezone: string;
    locale: string;
    theme: Appearance;
    email_verified: boolean;
    two_factor_enabled: boolean;
    is_super_admin: boolean;
    last_login_at: string | null;
    created_at: string | null;
}

export interface ProfilePreferences {
    timezone: string;
    locale: string;
    theme: Appearance;
    /** Free-form topic toggles merged into the user's `preferences` JSON bag. */
    notifications: Record<string, boolean>;
}

export interface ProfilePageProps {
    user: ProfileUser;
    preferences: ProfilePreferences;
    locales: Record<string, LocaleDefinition>;
    themes: EnumOption[];
}
