export interface EnumOption {
    value: string;
    label: string;
    color?: string;
}

export type OptionMap = Record<string, string>;

export interface GuestRow {
    id: number;
    uuid: string;
    hotel_id: number | null;
    hotel: string | null;
    first_name: string;
    last_name: string;
    full_name: string;
    gender: string | null;
    gender_label: string | null;
    date_of_birth: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    nationality: string | null;
    id_type: string | null;
    id_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    is_vip: boolean;
    is_blacklisted: boolean;
    reservations_count: number | null;
    created_at: string | null;
}

export interface ReservationRow {
    id: number;
    number: string;
    hotel_id: number;
    hotel: string | null;
    guest_id: number;
    guest: string | null;
    guest_email: string | null;
    guest_phone: string | null;
    room_id: number | null;
    room: string | null;
    bed_id: number | null;
    bed: string | null;
    room_type_id: number | null;
    room_type: string | null;
    check_in_date: string;
    check_out_date: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
    adults: number;
    children: number;
    rooms_count: number;
    nights: number;
    booking_source: string;
    booking_source_label: string;
    special_requests: string | null;
    notes: string | null;
    discount: number;
    tax: number;
    total: number;
    paid_amount: number;
    due_amount: number;
    payment_method?: string | null;
    payment_status?: string | null;
    payment_status_label?: string | null;
    payment_reference?: string | null;
    status: string;
    status_label: string;
    status_color: string;
    can_check_in: boolean;
    can_check_out: boolean;
    can_cancel: boolean;
    can_confirm: boolean;
    created_at: string | null;
}

export interface CalendarRoom {
    id: number;
    number: string;
    status: string;
    status_label: string;
    status_color: string;
    floor: string | null;
    room_type: string | null;
}

export interface CalendarReservation {
    id: number;
    number: string;
    room_id: number | null;
    guest: string | null;
    check_in_date: string;
    check_out_date: string;
    status: string;
    status_label: string;
    status_color: string;
}
