import type { EnumOption } from './users';

/*
|------------------------------------------------------------------------------
| Workspaces
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\Company\Http\Resources\* and the props the workspace
| controllers attach alongside them.
|
*/

export interface Company {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    owner_id: number;
    email: string | null;
    phone: string | null;
    website: string | null;
    tax_id: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country_code: string | null;
    timezone: string;
    currency: string;
    locale: string;
    is_active: boolean;
    logo: string | null;
    initials: string;
    on_trial: boolean;
    trial_ends_at: string | null;
    created_at: string | null;
}

export interface CompanyOwner {
    id: number;
    name: string;
    email: string;
}

export interface MemberRow {
    id: number;
    uuid: string;
    name: string;
    email: string;
    initials: string;
    job_title: string | null;
    status: string;
    role: string | null;
    role_label: string | null;
    role_color: string | null;
    department_id: number | null;
    department: string | null;
    joined_at: string | null;
    roles: string[];
}

export interface InvitationRow {
    id: number;
    /** The invitation routes bind by token, not by id. */
    token: string;
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

export interface DepartmentNode {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    parent: string | null;
    manager_id: number | null;
    manager: string | null;
    teams_count: number | null;
    children_count: number | null;
    children: DepartmentNode[];
    created_at: string | null;
}

export interface TeamMemberSummary {
    id: number;
    name: string;
    initials: string;
}

export interface TeamRow {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    description: string | null;
    department_id: number | null;
    department: string | null;
    lead_id: number | null;
    lead: string | null;
    members_count: number | null;
    members: TeamMemberSummary[];
    created_at: string | null;
}

export interface TransferCandidate {
    id: number;
    name: string;
    email: string;
}

/** `{ value: label }` maps the workspace controllers use for pickers. */
export type OptionMap = Record<string, string>;

export type CompanyRoleOption = Pick<EnumOption, 'value' | 'label'>;

/** The workspace profile payload shared by the create and edit forms. */
export interface CompanyFormValues {
    name: string;
    email: string;
    phone: string;
    website: string;
    tax_id: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    postal_code: string;
    country_code: string;
    timezone: string;
    currency: string;
    locale: string;
}
