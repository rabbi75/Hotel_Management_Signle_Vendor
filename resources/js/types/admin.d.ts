/*
|------------------------------------------------------------------------------
| Platform admin props
|------------------------------------------------------------------------------
|
| The mirror of the resources shared by App\Modules\Platform. Kept apart from
| the tenant-facing types in index.d.ts because these shapes only ever appear in
| the /admin panel.
|
*/

export interface TenantRow {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    logo: string | null;
    initials: string;
    is_active: boolean;
    email: string | null;
    phone?: string | null;
    website?: string | null;
    country_code: string | null;
    city?: string | null;
    timezone?: string | null;
    currency: string;
    locale?: string | null;
    trial_ends_at: string | null;
    created_at: string | null;
    owner?: { id: number; name: string; email: string };
    members_count: number | null;
    plan: string | null;
    plan_slug: string | null;
    subscription_status: string | null;
    subscription_interval: string | null;
    renews_at: string | null;
}

export interface TenantMember {
    id: number;
    name: string;
    email: string;
    role: string | null;
    status: string;
    joined_at: string | null;
    avatar?: string | null;
    initials?: string | null;
}

export interface TenantSubscriptionRow {
    id: number;
    plan: string;
    status: string;
    interval: string;
    gateway: string;
    trial_ends_at: string | null;
    current_period_end: string | null;
    ended_at: string | null;
}

export interface UsageMeter {
    key: string;
    label: string;
    limit: number;
    used: number;
    remaining: number;
    percentage: number | null;
}

export interface TenantHotelFootprint {
    hotels: number;
    rooms: number;
    workspaces: number;
    in_house_guests: number;
    arrivals_today: number;
    pending_housekeeping: number;
    open_maintenance: number;
    occupancy_rate: number;
}

export interface TenantAiSummary {
    enabled: boolean;
    period: string;
    allowance: number;
    used: number;
    reserved: number;
    available: number;
    generations_this_period: number;
    failed_this_period: number;
}

export interface TenantSupportNote {
    id: number;
    body: string;
    is_pinned: boolean;
    admin: string | null;
    created_at: string | null;
}

export interface PlatformAiUsageRow {
    company_id: number;
    uuid: string;
    name: string;
    is_active: boolean;
    ai_enabled: boolean;
    period: string;
    allowance: number;
    used: number;
    reserved: number;
    available: number;
    generations: number;
    failures: number;
}

export interface PlatformUserRow {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    initials: string;
    status: string;
    is_super_admin: boolean;
    workspaces: Array<{ uuid: string; name: string }>;
    last_login_at: string | null;
    created_at: string | null;
}

export interface PlatformSummary {
    tenants: { total: number; active: number; new_this_month: number };
    users: { total: number };
    subscriptions: { active: number; trialing: number; past_due: number; canceling: number };
    mrr: { amount: number; currency: string; formatted: string };
    trials_ending: Array<{ company: string; uuid: string; trial_ends_at: string }>;
}

export interface SignupPoint {
    month: string;
    count: number;
}
