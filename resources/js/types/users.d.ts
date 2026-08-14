import type { RoleSummary } from './roles';

/*
|------------------------------------------------------------------------------
| Users
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\User\Http\Resources\UserResource and the extra props the
| user screens receive from App\Modules\User\Http\Controllers\UserController.
|
*/

/** The shape `HasLabel::options()` produces for every backed enum in the app. */
export interface EnumOption {
    value: string;
    label: string;
    color: string;
}

export interface UserStatusMeta {
    value: string;
    label: string;
    color: string;
}

export interface UserCompanyMembership {
    id: number;
    name: string;
    role: string | null;
}

export interface UserRow {
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
    status: UserStatusMeta;
    timezone: string;
    locale: string;
    theme: string;
    email_verified: boolean;
    two_factor_enabled: boolean;
    is_super_admin: boolean;
    suspended_at: string | null;
    suspended_reason: string | null;
    last_login_at: string | null;
    created_at: string | null;
    deleted_at: string | null;
    /** Absent unless the controller eager-loaded the relation. */
    roles?: RoleSummary[];
    companies?: UserCompanyMembership[];
}

/** The trimmed login rows `UserController::show()` attaches to a profile. */
export interface UserLoginEntry {
    id: number;
    ip_address: string | null;
    platform: string | null;
    browser: string | null;
    successful: boolean;
    logged_in_at: string;
}

export interface UserAbilities {
    update: boolean;
    delete: boolean;
    suspend: boolean;
    impersonate: boolean;
}

/** `UserController::formOptions()`. */
export interface UserFormOptions {
    roles: string[];
    statuses: EnumOption[];
    company_roles: EnumOption[];
    themes: EnumOption[];
    locales: Record<string, { name: string; native: string; dir: 'ltr' | 'rtl' }>;
}

/** The payload StoreUserRequest / UpdateUserRequest validate. */
export interface UserFormValues {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    phone: string;
    job_title: string;
    bio: string;
    status: string;
    company_role: string;
    timezone: string;
    locale: string;
    theme: string;
    roles: string[];
    [key: string]: string | string[];
}
