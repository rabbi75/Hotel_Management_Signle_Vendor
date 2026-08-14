export type OptionMap = Record<string, string>;

export interface EnumOption {
    value: string;
    label: string;
    color?: string;
}

export interface HousekeepingTaskRow {
    id: number;
    number: string;
    hotel_id: number;
    hotel: string | null;
    room_id: number;
    room: string | null;
    reservation_id: number | null;
    reservation: string | null;
    status: string;
    status_label: string;
    status_color: string;
    priority: string;
    priority_label: string;
    priority_color: string;
    task_type: string;
    task_type_label: string;
    assigned_to: number | null;
    assignee: string | null;
    instructions: string | null;
    notes: string | null;
    scheduled_for: string | null;
    started_at: string | null;
    completed_at: string | null;
    is_open: boolean;
    can_start: boolean;
    can_complete: boolean;
    can_cancel: boolean;
    created_at: string | null;
}

export interface MaintenanceRequestRow {
    id: number;
    number: string;
    hotel_id: number;
    hotel: string | null;
    room_id: number | null;
    room: string | null;
    bed_id: number | null;
    bed: string | null;
    title: string;
    description: string | null;
    category: string;
    category_label: string;
    priority: string;
    priority_label: string;
    priority_color: string;
    status: string;
    status_label: string;
    status_color: string;
    blocks_room: boolean;
    assigned_to: number | null;
    assignee: string | null;
    reporter: string | null;
    due_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    resolution_notes: string | null;
    is_open: boolean;
    created_at: string | null;
}
