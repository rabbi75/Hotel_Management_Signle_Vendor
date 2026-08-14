import type { Config as ZiggyConfig } from 'ziggy-js';

/*
|------------------------------------------------------------------------------
| Shared Inertia props
|------------------------------------------------------------------------------
|
| The mirror of app/Http/Middleware/HandleInertiaRequests::share(). Any change
| on the PHP side must be reflected here — these types are what makes a page's
| props checkable at build time.
|
*/

export type Appearance = 'light' | 'dark' | 'system';

export interface AuthenticatedUser {
    id: number;
    uuid: string;
    name: string;
    first_name: string | null;
    last_name: string | null;
    email: string;
    email_verified: boolean;
    avatar: string | null;
    avatar_url?: string | null;
    initials: string;
    job_title: string | null;
    timezone: string;
    locale: string;
    theme: Appearance;
    status: 'active' | 'invited' | 'suspended';
    two_factor_enabled: boolean;
    is_super_admin: boolean;
}

export interface Impersonator {
    name: string;
    return_url: string | null;
}

/** The signed-in operator, on the `admin` guard. Present only in the console. */
export interface AdminSummary {
    id: number;
    uuid: string;
    name: string;
    email: string;
    initials: string;
    avatar: string | null;
    status: string;
    is_super_admin: boolean;
    roles: string[];
    two_factor_enabled: boolean;
    last_login_at: string | null;
    created_at: string | null;
}

export interface CompanySummary {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    logo: string | null;
    initials: string;
    role: 'owner' | 'admin' | 'member' | 'guest';
}

export interface WorkspaceSummary {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    logo: string | null;
    initials: string;
    role: 'admin' | 'manager' | 'member' | null;
    is_default: boolean;
}

export interface HotelSummary {
    id: number;
    uuid: string;
    name: string;
    status: string;
    status_label: string;
    is_active: boolean;
    logo: string | null;
}

export interface NavItem {
    label: string;
    route: string | null;
    href: string | null;
    icon: string | null;
    badge: string | null;
    activeWhen: string[];
    children: NavItem[];
}

export interface NavSection {
    label: string;
    items: NavItem[];
}

export interface NotificationItem {
    id: string;
    type: string;
    title: string;
    body: string | null;
    icon: string | null;
    level: 'info' | 'success' | 'warning' | 'critical';
    action_url: string | null;
    action_label: string | null;
    read_at: string | null;
    created_at: string;
    created_at_human: string;
}

export interface LocaleDefinition {
    name: string;
    native: string;
    dir: 'ltr' | 'rtl';
}

/**
 * The installation's brand identity, mirroring App\Support\Branding\Branding.
 *
 * Every asset is null when the operator has not uploaded one, which is what the
 * `BrandLogo` fallback chain keys on — never an empty string.
 */
export interface Branding {
    name: string;
    short_name: string;
    tagline: string | null;
    logo: string | null;
    dark_logo: string | null;
    icon: string | null;
    favicon: string | null;
    landing_logo: string | null;
    primary_color: string;
}

export interface SharedProps {
    name: string;
    branding: Branding;
    auth: {
        user: AuthenticatedUser | null;
        /** The console operator; null in the tenant app. */
        admin: AdminSummary | null;
        permissions: string[];
        /** Feature keys the active workspace's plan grants; see config/entitlements.php. */
        entitlements: string[];
        company: CompanySummary | null;
        companies: CompanySummary[];
        workspace: WorkspaceSummary | null;
        workspaces: WorkspaceSummary[];
        /** Active property for hotel modules; null when none selected. */
        hotel: HotelSummary | null;
        hotels: HotelSummary[];
        /** Set only while an administrator is signed in as another user. */
        impersonator: Impersonator | null;
    };
    navigation: NavSection[];
    notifications: {
        unread: number;
        items: NotificationItem[];
    };
    flash: {
        success: string | null;
        error: string | null;
        warning: string | null;
        info: string | null;
    };
    appearance: Appearance;
    sidebarOpen: boolean;
    locale: string;
    locales: Record<string, LocaleDefinition>;
    ziggy: ZiggyConfig & { location: string };
    errors: Record<string, string>;
    /** True when this install is a single hotel, not a SaaS platform. */
    singleVendor: boolean;
    /** Public booking URL when a property has online booking enabled. */
    bookingUrl: string | null;
    [key: string]: unknown;
}

/*
|------------------------------------------------------------------------------
| Data tables
|------------------------------------------------------------------------------
|
| The mirror of App\Support\DataTable\TableBuilder::toArray().
|
*/

export interface TableColumn {
    key: string;
    label: string;
    sortable: boolean;
    hidden: boolean;
    toggleable: boolean;
    align: 'left' | 'center' | 'right';
    width: string | null;
}

export interface TableFilterOption {
    value: string;
    label: string;
}

export interface TableFilter {
    key: string;
    label: string;
    type: 'select' | 'boolean' | 'date_range' | 'text';
    multiple: boolean;
    options: TableFilterOption[];
}

export interface TableMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface TableState {
    search: string | null;
    sort: string | null;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: Record<string, unknown>;
}

export interface TablePayload<TRow = Record<string, unknown>> {
    rows: TRow[];
    meta: TableMeta;
    columns: TableColumn[];
    filters: TableFilter[];
    state: TableState;
    per_page_options: number[];
}

/*
|------------------------------------------------------------------------------
| Misc
|------------------------------------------------------------------------------
*/

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

export interface SelectOption {
    value: string | number;
    label: string;
    description?: string;
    disabled?: boolean;
}
