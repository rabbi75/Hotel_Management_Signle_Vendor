/*
|------------------------------------------------------------------------------
| Roles & permissions
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\Role\Http\Resources\{RoleResource, PermissionResource},
| App\Modules\Role\Http\Controllers\PermissionMatrixController and the shape of
| config/permissions.php as it is handed to the role screens.
|
*/

export interface Permission {
    id: number;
    name: string;
    label: string;
    group: string;
    guard_name: string;
    declared: boolean;
}

export interface RoleSummary {
    id: number;
    name: string;
    label: string;
    description: string | null;
    guard_name: string;
    is_system: boolean;
    is_super_admin: boolean;
    /** Present only when the controller counted the relation. */
    users_count?: number;
    permissions_count?: number;
    /** Present only when the controller eager-loaded the relation. */
    permissions?: Permission[];
    created_at: string | null;
}

/** One entry of `config('permissions.groups')`, passed verbatim to the role form. */
export interface PermissionGroupConfig {
    label: string;
    /** permission name => human description. */
    permissions: Record<string, string>;
}

export type PermissionGroups = Record<string, PermissionGroupConfig>;

/*
| The matrix screen receives the same registry pre-flattened into lists, so the
| grid never has to rely on object key order.
*/

export interface MatrixPermission {
    name: string;
    label: string;
}

export interface MatrixGroup {
    key: string;
    label: string;
    permissions: MatrixPermission[];
}

export interface MatrixRole {
    id: number;
    name: string;
    label: string;
    permissions: string[];
}
